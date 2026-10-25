<?php

namespace App\Services\Regions;

use App\Filters\Regions\RegionFilter;
use App\Models\Regions\RegionVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RegionService
{
    public function __construct(private readonly RegionFilter $regionFilter) {}

    /** @param array{search?: string, level?: string, parent_region_id?: string, per_page?: int|string} $filters */
    public function publishedRegions(array $filters): LengthAwarePaginator
    {
        return $this->regionFilter->apply(RegionVersion::query()->published(), $filters)
            ->orderBy('name')
            ->orderBy('region_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findPublished(string $regionId): RegionVersion
    {
        return RegionVersion::query()
            ->published()
            ->where('region_id', $regionId)
            ->firstOrFail();
    }
}
