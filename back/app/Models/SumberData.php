<?php

namespace App\Models;

use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Proyek\VersiProyek;
use App\Models\Wilayah\VersiWilayah;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'source_type', 'source_url', 'license_info', 'notes'])]
class SumberData extends Model
{
    use HasUuids;

    protected $table = 'data_sources';

    /** @return HasMany<PekerjaanImpor, $this> */
    public function pekerjaanImpor(): HasMany
    {
        return $this->hasMany(PekerjaanImpor::class, 'source_id');
    }

    /** @return HasMany<VersiWilayah, $this> */
    public function versiWilayah(): HasMany
    {
        return $this->hasMany(VersiWilayah::class, 'source_id');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitas(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'source_id');
    }

    /** @return HasMany<VersiProyek, $this> */
    public function versiProyek(): HasMany
    {
        return $this->hasMany(VersiProyek::class, 'source_id');
    }

    /** @return HasMany<VersiDataset, $this> */
    public function versiDataset(): HasMany
    {
        return $this->hasMany(VersiDataset::class, 'source_id');
    }
}
