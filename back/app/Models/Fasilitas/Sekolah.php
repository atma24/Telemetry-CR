<?php

namespace App\Models\Fasilitas;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'facility_version_id', 'periode_data', 'npsn', 'kelurahan_sumber', 'kecamatan_sumber',
    'jumlah_siswa', 'jumlah_guru', 'kepala_sekolah', 'telp_sekolah', 'akreditasi',
])]
class Sekolah extends Model
{
    use HasUuids;

    protected $table = 'schools';

    /** @return BelongsTo<VersiFasilitas, $this> */
    public function versiFasilitas(): BelongsTo
    {
        return $this->belongsTo(VersiFasilitas::class, 'facility_version_id');
    }
}
