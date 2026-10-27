<?php

namespace App\Models\Fasilitas;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'facility_version_id', 'periode_data', 'wilayah_sumber', 'kecamatan_sumber',
    'kelurahan_sumber', 'kode_pos', 'telepon',
])]
class LokasiPasar extends Model
{
    use HasUuids;

    protected $table = 'market_locations';

    /** @return BelongsTo<VersiFasilitas, $this> */
    public function versiFasilitas(): BelongsTo
    {
        return $this->belongsTo(VersiFasilitas::class, 'facility_version_id');
    }
}
