<?php

namespace App\Services\Proyek;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Proyek\Proyek;
use App\Models\Proyek\VersiProyek;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProyekService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array{search?: string, category_id?: string, publication_status?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, VersiProyek>
     */
    public function adminProjects(array $filters): LengthAwarePaginator
    {
        return VersiProyek::query()->with(['proyek', 'kategori', 'wilayahCakupan.versiTerpublikasi'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('name', 'ilike', '%'.$search.'%'))
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('category_id', $id))
            ->when($filters['publication_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('publication_status', $status))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(max(1, min($filters['per_page'] ?? 15, 100)));
    }

    public function adminProject(string $projectId): Proyek
    {
        return Proyek::query()->with(['versi' => fn (HasMany $query) => $query->orderByDesc('version_no')->with(['kategori', 'wilayahCakupan.versiTerpublikasi'])])->findOrFail($projectId);
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(array $attributes, Pengguna $actor): VersiProyek
    {
        return DB::transaction(function () use ($attributes, $actor): VersiProyek {
            $regions = $attributes['regions'] ?? [];
            unset($attributes['regions']);
            $this->assertProjectData($attributes, $regions);
            $project = Proyek::query()->create();
            $version = $project->versi()->create([...$attributes, 'version_no' => 1, 'publication_status' => PublicationStatus::Draft, 'created_by' => $actor->id]);
            $this->syncRegions($version, $regions);
            $this->auditLogService->record($actor, 'project.created', 'project', $project->id, null, $this->auditData($version));

            return $version->load('wilayahCakupan.versiTerpublikasi');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createVersion(string $projectId, array $attributes, Pengguna $actor): VersiProyek
    {
        return DB::transaction(function () use ($projectId, $attributes, $actor): VersiProyek {
            $project = Proyek::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            $latest = $project->versi()->orderByDesc('version_no')->lockForUpdate()->firstOrFail();
            $regionsProvided = array_key_exists('regions', $attributes);
            $regions = $regionsProvided ? $attributes['regions'] : $latest->wilayahCakupan()->get()->map(fn ($region): array => ['region_id' => $region->id, 'is_primary' => (bool) $region->pivot->is_primary])->all();
            unset($attributes['regions']);
            $data = array_merge($latest->only(['name', 'category_id', 'agency', 'start_date', 'planned_end_date', 'progress_percentage', 'project_status', 'attributes', 'source_id']), $attributes);
            $this->assertProjectData($data, $regions);
            $version = $project->versi()->create([...$data, 'version_no' => $latest->version_no + 1, 'publication_status' => PublicationStatus::Draft, 'created_by' => $actor->id]);
            $this->syncRegions($version, $regions);
            $this->auditLogService->record($actor, 'project.version_created', 'project', $project->id, $this->auditData($latest), $this->auditData($version));

            return $version->load('wilayahCakupan.versiTerpublikasi');
        });
    }

    public function validateVersion(string $projectId, string $versionId, Pengguna $actor): VersiProyek
    {
        return DB::transaction(function () use ($projectId, $versionId, $actor): VersiProyek {
            $version = $this->lockedVersion($projectId, $versionId);
            if ($version->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages(['publication_status' => 'Only draft project versions can be validated.']);
            }
            $this->assertProjectData($version->only(['name', 'category_id', 'agency', 'start_date', 'planned_end_date', 'progress_percentage', 'project_status', 'attributes', 'source_id']), $version->wilayahCakupan()->get()->map(fn ($region): array => ['region_id' => $region->id])->all());
            $version->forceFill(['publication_status' => PublicationStatus::Validating, 'reviewed_by' => $actor->id])->save();
            $this->auditLogService->record($actor, 'project.validated', 'project', $projectId, ['version_id' => $versionId, 'publication_status' => PublicationStatus::Draft->value], ['publication_status' => PublicationStatus::Validating->value]);

            return $version->refresh()->load('wilayahCakupan.versiTerpublikasi');
        });
    }

    public function publishVersion(string $projectId, string $versionId, Pengguna $actor): VersiProyek
    {
        return DB::transaction(function () use ($projectId, $versionId, $actor): VersiProyek {
            $project = Proyek::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            $version = $project->versi()->whereKey($versionId)->lockForUpdate()->firstOrFail();
            if ($version->publication_status !== PublicationStatus::Validating) {
                throw ValidationException::withMessages(['publication_status' => 'Only validated project versions can be published.']);
            }
            $regions = $version->wilayahCakupan()->get()->map(fn ($region): array => ['region_id' => $region->id])->all();
            $this->assertProjectData($version->only(['name', 'category_id', 'agency', 'start_date', 'planned_end_date', 'progress_percentage', 'project_status', 'attributes', 'source_id']), $regions);
            $project->versi()->where('publication_status', PublicationStatus::Published)->update(['publication_status' => PublicationStatus::Superseded, 'updated_at' => now()]);
            $version->forceFill(['publication_status' => PublicationStatus::Published, 'reviewed_by' => $actor->id, 'published_at' => now()])->save();
            $this->auditLogService->record($actor, 'project.published', 'project', $projectId, null, ['version_id' => $versionId, 'version_no' => $version->version_no]);

            return $version->refresh()->load('wilayahCakupan.versiTerpublikasi');
        });
    }

    public function archiveProject(string $projectId, Pengguna $actor): VersiProyek
    {
        return DB::transaction(function () use ($projectId, $actor): VersiProyek {
            $project = Proyek::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            $version = $project->versi()->where('publication_status', PublicationStatus::Published)->lockForUpdate()->first();
            if ($version === null) {
                throw ValidationException::withMessages(['project' => 'The project has no published version to archive.']);
            }
            $version->forceFill(['publication_status' => PublicationStatus::Archived])->save();
            $this->auditLogService->record($actor, 'project.archived', 'project', $projectId, ['version_id' => $version->id, 'publication_status' => PublicationStatus::Published->value], ['publication_status' => PublicationStatus::Archived->value]);

            return $version->refresh()->load('wilayahCakupan.versiTerpublikasi');
        });
    }

    private function lockedVersion(string $projectId, string $versionId): VersiProyek
    {
        return VersiProyek::query()->where('project_id', $projectId)->whereKey($versionId)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $regions */
    private function assertProjectData(array $data, array $regions): void
    {
        if (! is_string($data['name'] ?? null) || trim($data['name']) === '' || mb_strlen($data['name']) > 255) {
            throw ValidationException::withMessages(['name' => 'A project name of up to 255 characters is required.']);
        }
        if (! is_string($data['category_id'] ?? null) || ! Kategori::query()->whereKey($data['category_id'])->exists()) {
            throw ValidationException::withMessages(['category_id' => 'A valid project category is required.']);
        }
        if (($data['start_date'] ?? null) && ($data['planned_end_date'] ?? null) && $data['planned_end_date'] < $data['start_date']) {
            throw ValidationException::withMessages(['planned_end_date' => 'Planned end date must be on or after the start date.']);
        }
        if (isset($data['progress_percentage']) && (! is_numeric($data['progress_percentage']) || $data['progress_percentage'] < 0 || $data['progress_percentage'] > 100)) {
            throw ValidationException::withMessages(['progress_percentage' => 'Progress must be between 0 and 100.']);
        }
        if (isset($data['attributes']) && ! is_array($data['attributes'])) {
            throw ValidationException::withMessages(['attributes' => 'Project attributes must be an object.']);
        }
        $primaryCount = count(array_filter($regions, fn (array $region): bool => (bool) ($region['is_primary'] ?? false)));
        if ($primaryCount > 1) {
            throw ValidationException::withMessages(['regions' => 'A project version may have at most one primary region.']);
        }
        foreach ($regions as $region) {
            if (! VersiWilayah::query()->terpublikasi()->where('region_id', $region['region_id'])->exists()) {
                throw ValidationException::withMessages(['regions' => 'Every project region must have a currently published version.']);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $regions */
    private function syncRegions(VersiProyek $version, array $regions): void
    {
        $version->wilayahCakupan()->sync(collect($regions)->mapWithKeys(fn (array $region): array => [
            $region['region_id'] => ['is_primary' => (bool) ($region['is_primary'] ?? false)],
        ])->all());
    }

    /** @return array<string, mixed> */
    private function auditData(VersiProyek $version): array
    {
        return $version->only(['id', 'version_no', 'name', 'category_id', 'publication_status', 'source_id']);
    }
}
