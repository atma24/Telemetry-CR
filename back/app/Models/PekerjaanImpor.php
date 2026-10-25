<?php

namespace App\Models;

use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Proyek\VersiProyek;
use App\Models\Wilayah\VersiWilayah;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'target_type', 'source_id', 'original_filename', 'file_path', 'status', 'total_rows',
    'accepted_rows', 'rejected_rows', 'initiated_by', 'finished_at',
])]
class PekerjaanImpor extends Model
{
    use HasUuids;

    protected $table = 'import_jobs';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SumberData, $this> */
    public function sumberData(): BelongsTo
    {
        return $this->belongsTo(SumberData::class, 'source_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'initiated_by');
    }

    /** @return HasMany<VersiWilayah, $this> */
    public function versiWilayah(): HasMany
    {
        return $this->hasMany(VersiWilayah::class, 'import_job_id');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitas(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'import_job_id');
    }

    /** @return HasMany<VersiProyek, $this> */
    public function versiProyek(): HasMany
    {
        return $this->hasMany(VersiProyek::class, 'import_job_id');
    }

    /** @return HasMany<VersiDataset, $this> */
    public function versiDataset(): HasMany
    {
        return $this->hasMany(VersiDataset::class, 'import_job_id');
    }
}
