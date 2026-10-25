<?php

namespace App\Models\Regions;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionVersion extends Model
{
    use HasUuids;

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('publication_status', 'published');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function parentRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_region_id');
    }
}
