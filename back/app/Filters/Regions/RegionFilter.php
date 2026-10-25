<?php

namespace App\Filters\Regions;

use Illuminate\Database\Eloquent\Builder;

class RegionFilter
{
    /** @param array{search?: string, level?: string, parent_region_id?: string} $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('code', 'ilike', '%'.$search.'%');
                });
            })
            ->when($filters['level'] ?? null, fn (Builder $query, string $level): Builder => $query->where('level', $level))
            ->when(
                $filters['parent_region_id'] ?? null,
                fn (Builder $query, string $parentRegionId): Builder => $query->where('parent_region_id', $parentRegionId),
            );
    }
}
