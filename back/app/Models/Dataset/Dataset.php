<?php

namespace App\Models\Dataset;

use App\Models\Feedback\UmpanBalik;
use App\Models\Kategori;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'slug', 'description', 'category_id', 'lifecycle_status', 'created_by'])]
class Dataset extends Model
{
    use HasUuids;

    protected $table = 'datasets';

    /** @return HasMany<VersiDataset, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(VersiDataset::class, 'dataset_id');
    }

    /** @return HasOne<VersiDataset, $this> */
    public function versiTerpublikasi(): HasOne
    {
        return $this->hasOne(VersiDataset::class, 'dataset_id')
            ->where('publication_status', 'published');
    }

    /** @return HasOne<VersiDataset, $this> */
    public function versiTerbaru(): HasOne
    {
        return $this->hasOne(VersiDataset::class, 'dataset_id')->ofMany('version_no', 'max');
    }

    /** @return BelongsTo<Kategori, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'category_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'created_by');
    }

    /** @return HasMany<UmpanBalik, $this> */
    public function umpanBalik(): HasMany
    {
        return $this->hasMany(UmpanBalik::class, 'dataset_id');
    }
}
