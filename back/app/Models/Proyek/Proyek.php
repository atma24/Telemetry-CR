<?php

namespace App\Models\Proyek;

use App\Models\Feedback\UmpanBalik;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['external_key'])]
class Proyek extends Model
{
    use HasUuids;

    protected $table = 'projects';

    /** @return HasMany<VersiProyek, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(VersiProyek::class, 'project_id');
    }

    /** @return HasOne<VersiProyek, $this> */
    public function versiTerpublikasi(): HasOne
    {
        return $this->hasOne(VersiProyek::class, 'project_id')
            ->where('publication_status', 'published');
    }

    /** @return HasOne<VersiProyek, $this> */
    public function versiTerbaru(): HasOne
    {
        return $this->hasOne(VersiProyek::class, 'project_id')->ofMany('version_no', 'max');
    }

    /** @return HasMany<UmpanBalik, $this> */
    public function umpanBalik(): HasMany
    {
        return $this->hasMany(UmpanBalik::class, 'project_id');
    }
}
