<?php

namespace App\Models\Feedback;

use App\Models\Apbd\Apbd;
use App\Models\Dataset\Dataset;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Pengguna;
use App\Models\Wilayah\Wilayah;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'facility_id', 'apbd_id', 'region_id', 'dataset_id', 'subject', 'message', 'status', 'rejection_reason',
])]
class UmpanBalik extends Model
{
    use HasUuids;

    protected $table = 'feedbacks';

    /** @return BelongsTo<Pengguna, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'user_id');
    }

    /** @return BelongsTo<Fasilitas, $this> */
    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(Fasilitas::class, 'facility_id');
    }

    /** @return BelongsTo<Apbd, $this> */
    public function apbd(): BelongsTo
    {
        return $this->belongsTo(Apbd::class, 'apbd_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'dataset_id');
    }

    /** @return HasMany<RiwayatUmpanBalik, $this> */
    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatUmpanBalik::class, 'feedback_id');
    }
}
