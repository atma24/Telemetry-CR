<?php

namespace App\Models\Wilayah;

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
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $region_id
 * @property int $version_no
 * @property string $code
 * @property string $name
 * @property string $level
 * @property string|null $parent_region_id
 * @property string|null $source_id
 * @property array<string, mixed>|null $source_original_data
 * @property PublicationStatus $publication_status
 * @property string $created_by
 * @property string|null $reviewed_by
 * @property Carbon|null $published_at
 * @property-read Wilayah|null $wilayahInduk
 */
#[Fillable([
    'version_no', 'code', 'name', 'level', 'parent_region_id', 'source_id', 'import_job_id',
    'source_original_data', 'publication_status', 'created_by', 'reviewed_by', 'published_at',
])]
class VersiWilayah extends Model
{
    use HasUuids;

    protected $table = 'region_versions';

    /** @param Builder<VersiWilayah> $query */
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
            'source_original_data' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayahInduk(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'parent_region_id');
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
