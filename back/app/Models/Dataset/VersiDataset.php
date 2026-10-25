<?php

namespace App\Models\Dataset;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\SumberData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $version_no
 * @property array<string, mixed>|null $schema_definition
 * @property Carbon|null $published_at
 * @property PublicationStatus $publication_status
 */
#[Fillable([
    'version_no', 'base_version_id', 'source_id', 'import_job_id', 'publication_status', 'schema_definition',
    'original_file_path', 'created_by', 'reviewed_by', 'published_at',
])]
class VersiDataset extends Model
{
    use HasUuids;

    protected $table = 'dataset_versions';

    /** @param Builder<VersiDataset> $query */
    #[Scope]
    protected function terpublikasi(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::Published->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'schema_definition' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'dataset_id');
    }

    /** @return BelongsTo<self, $this> */
    public function versiDasar(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_version_id');
    }

    /** @return HasMany<self, $this> */
    public function versiTurunan(): HasMany
    {
        return $this->hasMany(self::class, 'base_version_id');
    }

    /** @return HasMany<BarisDataset, $this> */
    public function baris(): HasMany
    {
        return $this->hasMany(BarisDataset::class, 'dataset_version_id');
    }

    /** @return BelongsTo<SumberData, $this> */
    public function sumberData(): BelongsTo
    {
        return $this->belongsTo(SumberData::class, 'source_id');
    }

    /** @return BelongsTo<PekerjaanImpor, $this> */
    public function pekerjaanImpor(): BelongsTo
    {
        return $this->belongsTo(PekerjaanImpor::class, 'import_job_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'created_by');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'reviewed_by');
    }
}
