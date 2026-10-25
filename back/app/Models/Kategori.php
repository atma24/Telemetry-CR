<?php

namespace App\Models;

use App\Models\Dataset\Dataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Proyek\VersiProyek;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug'])]
class Kategori extends Model
{
    use HasUuids;

    protected $table = 'categories';

    /** @return BelongsTo<self, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function turunan(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitas(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'category_id');
    }

    /** @return HasMany<VersiProyek, $this> */
    public function versiProyek(): HasMany
    {
        return $this->hasMany(VersiProyek::class, 'category_id');
    }

    /** @return HasMany<Dataset, $this> */
    public function dataset(): HasMany
    {
        return $this->hasMany(Dataset::class, 'category_id');
    }
}
