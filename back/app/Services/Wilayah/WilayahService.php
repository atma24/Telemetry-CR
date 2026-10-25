<?php

namespace App\Services\Wilayah;

use App\Enums\Wilayah\PublicationStatus;
use App\Enums\Wilayah\RegionLevel;
use App\Filters\Wilayah\RegionFilter;
use App\Models\User;
use App\Models\Wilayah\Region;
use App\Models\Wilayah\RegionVersion;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WilayahService
{
    public function __construct(
        private readonly RegionFilter $regionFilter,
        private readonly AuditLogService $auditLogService,
    ) {}

    /** @param array{search?: string, level?: string, parent_region_id?: string, per_page?: int|string} $filters */
    public function publishedRegions(array $filters): LengthAwarePaginator
    {
        return $this->regionFilter->apply(RegionVersion::query()->published()->with('parentRegion.publishedVersion'), $filters)
            ->orderBy('name')
            ->orderBy('region_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findPublished(string $regionId): RegionVersion
    {
        return RegionVersion::query()
            ->published()
            ->with('parentRegion.publishedVersion')
            ->where('region_id', $regionId)
            ->firstOrFail();
    }

    /** @param array{search?: string, level?: string, parent_region_id?: string, publication_status?: string, per_page?: int|string} $filters */
    public function adminRegions(array $filters): LengthAwarePaginator
    {
        return $this->regionFilter->apply(
            RegionVersion::query()->with('region'),
            $filters,
        )
            ->when(
                $filters['publication_status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('publication_status', $status),
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function adminRegion(string $regionId): Region
    {
        return Region::query()->with(['versions' => fn (Builder $query): Builder => $query->orderByDesc('version_no')])
            ->findOrFail($regionId);
    }

    /** @param array{code: string, name: string, level: string, parent_region_id?: string|null, source_id?: string|null, source_original_data?: array<string, mixed>|null} $attributes */
    public function createDraft(array $attributes, User $actor): RegionVersion
    {
        return DB::transaction(function () use ($attributes, $actor): RegionVersion {
            $this->assertHierarchy($attributes['level'], $attributes['parent_region_id'] ?? null);

            $region = Region::query()->create();
            $version = $region->versions()->create([
                ...$attributes,
                'version_no' => 1,
                'publication_status' => PublicationStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->record($actor, 'region.created', 'region', $region->id, null, $this->auditData($version));

            return $version;
        });
    }

    /** @param array{code?: string, name?: string, level?: string, parent_region_id?: string|null, source_id?: string|null, source_original_data?: array<string, mixed>|null} $attributes */
    public function createVersion(string $regionId, array $attributes, User $actor): RegionVersion
    {
        return DB::transaction(function () use ($regionId, $attributes, $actor): RegionVersion {
            $region = Region::query()->whereKey($regionId)->lockForUpdate()->firstOrFail();
            $latest = $region->versions()->orderByDesc('version_no')->lockForUpdate()->firstOrFail();
            $data = array_merge($latest->only([
                'code', 'name', 'level', 'parent_region_id', 'source_id', 'source_original_data',
            ]), $attributes);

            $this->assertHierarchy($data['level'], $data['parent_region_id']);

            $version = $region->versions()->create([
                ...$data,
                'version_no' => $latest->version_no + 1,
                'publication_status' => PublicationStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->record($actor, 'region.version_created', 'region', $region->id, $this->auditData($latest), $this->auditData($version));

            return $version;
        });
    }

    public function validateVersion(string $regionId, string $versionId, User $actor): RegionVersion
    {
        return DB::transaction(function () use ($regionId, $versionId, $actor): RegionVersion {
            $version = $this->lockedVersion($regionId, $versionId);

            if ($version->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages(['publication_status' => 'Only draft region versions can be validated.']);
            }

            $this->assertHierarchy($version->level, $version->parent_region_id);
            $version->forceFill([
                'publication_status' => PublicationStatus::Validating,
                'reviewed_by' => $actor->id,
            ])->save();

            $this->auditLogService->record($actor, 'region.validated', 'region', $regionId, ['publication_status' => PublicationStatus::Draft->value], ['version_id' => $versionId, 'publication_status' => PublicationStatus::Validating->value]);

            return $version->refresh();
        });
    }

    public function publishVersion(string $regionId, string $versionId, User $actor): RegionVersion
    {
        return DB::transaction(function () use ($regionId, $versionId, $actor): RegionVersion {
            $region = Region::query()->whereKey($regionId)->lockForUpdate()->firstOrFail();
            $version = $region->versions()->whereKey($versionId)->lockForUpdate()->firstOrFail();

            if ($version->publication_status !== PublicationStatus::Validating) {
                throw ValidationException::withMessages(['publication_status' => 'Only validated region versions can be published.']);
            }

            $this->assertHierarchy($version->level, $version->parent_region_id);
            $region->versions()->where('publication_status', PublicationStatus::Published)->update([
                'publication_status' => PublicationStatus::Superseded,
                'updated_at' => now(),
            ]);
            $version->forceFill([
                'publication_status' => PublicationStatus::Published,
                'reviewed_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            $this->auditLogService->record($actor, 'region.published', 'region', $regionId, null, ['version_id' => $versionId, 'version_no' => $version->version_no]);

            return $version->refresh();
        });
    }

    public function archiveRegion(string $regionId, User $actor): RegionVersion
    {
        return DB::transaction(function () use ($regionId, $actor): RegionVersion {
            $region = Region::query()->whereKey($regionId)->lockForUpdate()->firstOrFail();
            $version = $region->versions()->where('publication_status', PublicationStatus::Published)->lockForUpdate()->first();

            if ($version === null) {
                throw ValidationException::withMessages(['region' => 'The region has no published version to archive.']);
            }

            $version->forceFill(['publication_status' => PublicationStatus::Archived])->save();
            $this->auditLogService->record($actor, 'region.archived', 'region', $regionId, ['version_id' => $version->id, 'publication_status' => PublicationStatus::Published->value], ['publication_status' => PublicationStatus::Archived->value]);

            return $version->refresh();
        });
    }

    private function lockedVersion(string $regionId, string $versionId): RegionVersion
    {
        return RegionVersion::query()
            ->where('region_id', $regionId)
            ->whereKey($versionId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertHierarchy(string $level, ?string $parentRegionId): void
    {
        $regionLevel = RegionLevel::tryFrom($level);

        if ($regionLevel === null) {
            throw ValidationException::withMessages(['level' => 'The selected region level is invalid.']);
        }

        if ($regionLevel === RegionLevel::Province) {
            if ($parentRegionId !== null) {
                throw ValidationException::withMessages(['parent_region_id' => 'A province cannot have a parent region.']);
            }

            return;
        }

        if ($parentRegionId === null) {
            throw ValidationException::withMessages(['parent_region_id' => 'This region level requires a parent region.']);
        }

        $parentLevel = RegionVersion::query()
            ->published()
            ->where('region_id', $parentRegionId)
            ->value('level');

        if ($parentLevel === null || ! in_array($parentLevel, $regionLevel->allowedParentLevels(), true)) {
            throw ValidationException::withMessages(['parent_region_id' => 'The parent region is missing, unpublished, or has an invalid level.']);
        }
    }

    /** @return array<string, mixed> */
    private function auditData(RegionVersion $version): array
    {
        return $version->only(['id', 'version_no', 'code', 'name', 'level', 'parent_region_id', 'source_id', 'publication_status']);
    }
}
