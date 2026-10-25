<?php

namespace App\Models\Wilayah;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Region extends Model
{
    use HasUuids;

    public function versions(): HasMany
    {
        return $this->hasMany(RegionVersion::class);
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(RegionVersion::class)
            ->where('publication_status', 'published');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(RegionVersion::class)->ofMany('version_no', 'max');
    }

    #[Scope]
    protected function withPublishedVersion(Builder $query): void
    {
        $query->whereHas('publishedVersion')->with('publishedVersion');
    }
}
