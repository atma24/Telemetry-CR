<?php

namespace App\Services\Fasilitas;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FasilitasService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return $this->publishedFacilities()->orderBy('facility_versions.name')->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $facilityId): object
    {
        return $this->publishedFacilities()->where('facilities.id', $facilityId)->firstOrFail();
    }

    private function publishedFacilities(): Builder
    {
        return DB::table('facility_versions')
            ->join('facilities', 'facilities.id', '=', 'facility_versions.facility_id')
            ->where('facility_versions.publication_status', 'published')
            ->select([
                'facilities.id', 'facility_versions.id as version_id', 'facility_versions.version_no',
                'facility_versions.name', 'facility_versions.category_id', 'facility_versions.region_id',
                'facility_versions.address', 'facility_versions.latitude', 'facility_versions.longitude',
                'facility_versions.attributes', 'facility_versions.published_at',
            ]);
    }
}
