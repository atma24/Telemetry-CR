<?php

namespace App\Services\Fasilitas;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminFasilitasService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @param  array{search?: string, category_id?: string, region_id?: string, publication_status?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, VersiFasilitas>
     */
    public function adminFacilities(array $filters): LengthAwarePaginator
    {
        return VersiFasilitas::query()
            ->with(['fasilitas', 'kategori', 'wilayah.versiTerpublikasi', 'sumberData'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'ilike', '%'.$search.'%');
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('category_id', $id))
            ->when($filters['region_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('region_id', $id))
            ->when(
                $filters['publication_status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('publication_status', $status),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($filters['per_page'] ?? 15, 100)));
    }

    public function adminFacility(string $facilityId): Fasilitas
    {
        return Fasilitas::query()
            ->with(['versi' => fn (HasMany $query) => $query->orderByDesc('version_no')->with([
                'kategori', 'wilayah.versiTerpublikasi', 'sumberData',
            ])])
            ->findOrFail($facilityId);
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(array $attributes, Pengguna $actor): VersiFasilitas
    {
        return DB::transaction(function () use ($attributes, $actor): VersiFasilitas {
            $this->assertFacilityData($attributes);

            $facility = Fasilitas::query()->create();
            $version = $facility->versi()->create([
                ...$attributes,
                'version_no' => 1,
                'publication_status' => PublicationStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->record(
                $actor,
                'facility.created',
                'facility',
                $facility->id,
                null,
                $this->auditData($version),
            );

            return $version;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createVersion(string $facilityId, array $attributes, Pengguna $actor): VersiFasilitas
    {
        return DB::transaction(function () use ($facilityId, $attributes, $actor): VersiFasilitas {
            $facility = Fasilitas::query()->whereKey($facilityId)->lockForUpdate()->firstOrFail();
            $latest = $facility->versi()->orderByDesc('version_no')->lockForUpdate()->firstOrFail();
            $data = array_merge($latest->only([
                'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude',
                'attributes', 'source_id', 'source_original_data',
            ]), $attributes);

            $this->assertFacilityData($data);

            $version = $facility->versi()->create([
                ...$data,
                'version_no' => $latest->version_no + 1,
                'publication_status' => PublicationStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->record(
                $actor,
                'facility.version_created',
                'facility',
                $facility->id,
                $this->auditData($latest),
                $this->auditData($version),
            );

            return $version;
        });
    }

    public function validateVersion(string $facilityId, string $versionId, Pengguna $actor): VersiFasilitas
    {
        return DB::transaction(function () use ($facilityId, $versionId, $actor): VersiFasilitas {
            $version = $this->lockedVersion($facilityId, $versionId);

            if ($version->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only draft facility versions can be validated.',
                ]);
            }

            $this->assertFacilityData($version->only([
                'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude', 'attributes',
            ]));
            $version->forceFill([
                'publication_status' => PublicationStatus::Validating,
                'reviewed_by' => $actor->id,
            ])->save();

            $this->auditLogService->record(
                $actor,
                'facility.validated',
                'facility',
                $facilityId,
                ['publication_status' => PublicationStatus::Draft->value],
                ['version_id' => $versionId, 'publication_status' => PublicationStatus::Validating->value],
            );

            return $version->refresh();
        });
    }

    public function publishVersion(string $facilityId, string $versionId, Pengguna $actor): VersiFasilitas
    {
        return DB::transaction(function () use ($facilityId, $versionId, $actor): VersiFasilitas {
            $facility = Fasilitas::query()->whereKey($facilityId)->lockForUpdate()->firstOrFail();
            $version = $facility->versi()->whereKey($versionId)->lockForUpdate()->firstOrFail();

            if ($version->publication_status !== PublicationStatus::Validating) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only validated facility versions can be published.',
                ]);
            }

            $this->assertFacilityData($version->only([
                'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude', 'attributes',
            ]));
            $facility->versi()->where('publication_status', PublicationStatus::Published)->update([
                'publication_status' => PublicationStatus::Superseded,
                'updated_at' => now(),
            ]);
            $version->forceFill([
                'publication_status' => PublicationStatus::Published,
                'reviewed_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            $this->auditLogService->record(
                $actor,
                'facility.published',
                'facility',
                $facilityId,
                null,
                ['version_id' => $versionId, 'version_no' => $version->version_no],
            );

            return $version->refresh();
        });
    }

    public function archiveFacility(string $facilityId, Pengguna $actor): VersiFasilitas
    {
        return DB::transaction(function () use ($facilityId, $actor): VersiFasilitas {
            $facility = Fasilitas::query()->whereKey($facilityId)->lockForUpdate()->firstOrFail();
            $version = $facility->versi()
                ->where('publication_status', PublicationStatus::Published)
                ->lockForUpdate()
                ->first();

            if ($version === null) {
                throw ValidationException::withMessages([
                    'facility' => 'The facility has no published version to archive.',
                ]);
            }

            $version->forceFill(['publication_status' => PublicationStatus::Archived])->save();
            $this->auditLogService->record(
                $actor,
                'facility.archived',
                'facility',
                $facilityId,
                ['version_id' => $version->id, 'publication_status' => PublicationStatus::Published->value],
                ['publication_status' => PublicationStatus::Archived->value],
            );

            return $version->refresh();
        });
    }

    private function lockedVersion(string $facilityId, string $versionId): VersiFasilitas
    {
        return VersiFasilitas::query()
            ->where('facility_id', $facilityId)
            ->whereKey($versionId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function assertFacilityData(array $attributes): void
    {
        $name = $attributes['name'] ?? null;
        $categoryId = $attributes['category_id'] ?? null;
        $regionId = $attributes['region_id'] ?? null;

        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'A facility name of up to 255 characters is required.']);
        }

        if (! is_string($categoryId) || ! is_string($regionId)) {
            throw ValidationException::withMessages([
                'category_id' => 'A facility category is required.',
                'region_id' => 'A facility region is required.',
            ]);
        }

        if (! Kategori::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'The selected facility category does not exist.']);
        }

        if (! VersiWilayah::query()->terpublikasi()->where('region_id', $regionId)->exists()) {
            throw ValidationException::withMessages([
                'region_id' => 'The facility must reference a currently published region.',
            ]);
        }

        $facilityAttributes = $attributes['attributes'] ?? null;

        if ($facilityAttributes !== null && ! is_array($facilityAttributes)) {
            throw ValidationException::withMessages(['attributes' => 'Facility attributes must be an object.']);
        }

        $accessibilityAttributes = $facilityAttributes['accessibility'] ?? null;

        if ($accessibilityAttributes !== null && ! is_array($accessibilityAttributes)) {
            throw ValidationException::withMessages([
                'attributes.accessibility' => 'Accessibility attributes must be an object.',
            ]);
        }

        foreach ($accessibilityAttributes ?? [] as $attribute => $value) {
            if ($value !== null && ! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages([
                    'attributes.accessibility.'.$attribute => 'Accessibility values must be boolean or null.',
                ]);
            }
        }

        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;

        if (($latitude === null) !== ($longitude === null)) {
            throw ValidationException::withMessages([
                'latitude' => 'Latitude and longitude must both be numeric or both be null.',
                'longitude' => 'Latitude and longitude must both be numeric or both be null.',
            ]);
        }

        if ($latitude !== null && (! is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90)) {
            throw ValidationException::withMessages(['latitude' => 'Latitude must be between -90 and 90.']);
        }

        if ($longitude !== null && (! is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180)) {
            throw ValidationException::withMessages(['longitude' => 'Longitude must be between -180 and 180.']);
        }
    }

    /** @return array<string, mixed> */
    private function auditData(VersiFasilitas $version): array
    {
        return $version->only([
            'id', 'version_no', 'name', 'category_id', 'region_id', 'publication_status', 'source_id',
        ]);
    }
}
