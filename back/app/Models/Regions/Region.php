<?php

namespace App\Models\Regions;

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
}
