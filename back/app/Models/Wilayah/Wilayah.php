<?php

namespace App\Models\Wilayah;

use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Proyek\CakupanWilayahProyek;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wilayah extends Model
{
    use HasUuids;

    protected $table = 'regions';

    /** @return HasMany<VersiWilayah, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(VersiWilayah::class, 'region_id');
    }

    /** @return HasOne<VersiWilayah, $this> */
    public function versiTerpublikasi(): HasOne
    {
        return $this->hasOne(VersiWilayah::class, 'region_id')
            ->where('publication_status', 'published');
    }

    /** @return HasOne<VersiWilayah, $this> */
    public function versiTerbaru(): HasOne
    {
        return $this->hasOne(VersiWilayah::class, 'region_id')->ofMany('version_no', 'max');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitas(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'region_id');
    }

    /** @return HasMany<CakupanWilayahProyek, $this> */
    public function cakupanProyek(): HasMany
    {
        return $this->hasMany(CakupanWilayahProyek::class, 'region_id');
    }
}
