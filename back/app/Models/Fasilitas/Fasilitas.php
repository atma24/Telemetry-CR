<?php

namespace App\Models\Fasilitas;

use App\Models\Feedback\UmpanBalik;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property-read VersiFasilitas|null $versiTerpublikasi
 */
#[Fillable(['external_key'])]
class Fasilitas extends Model
{
    use HasUuids;

    protected $table = 'facilities';

    /** @return HasMany<VersiFasilitas, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'facility_id');
    }

    /** @return HasOne<VersiFasilitas, $this> */
    public function versiTerpublikasi(): HasOne
    {
        return $this->hasOne(VersiFasilitas::class, 'facility_id')
            ->where('publication_status', 'published');
    }

    /** @return HasOne<VersiFasilitas, $this> */
    public function versiTerbaru(): HasOne
    {
        return $this->hasOne(VersiFasilitas::class, 'facility_id')
            ->orderByDesc('version_no')
            ->limit(1);
    }

    /** @return HasMany<UmpanBalik, $this> */
    public function umpanBalik(): HasMany
    {
        return $this->hasMany(UmpanBalik::class, 'facility_id');
    }
}
