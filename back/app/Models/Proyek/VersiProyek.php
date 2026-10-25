<?php

namespace App\Models\Proyek;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Kategori;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\SumberData;
use App\Models\Wilayah\Wilayah;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $category_id
 * @property string|null $agency
 * @property Carbon|null $start_date
 * @property Carbon|null $planned_end_date
 * @property string|null $progress_percentage
 * @property string|null $project_status
 * @property array<string, mixed>|null $attributes
 * @property PublicationStatus $publication_status
 * @property Carbon|null $published_at
 */
#[Fillable([
    'version_no', 'name', 'category_id', 'agency', 'start_date', 'planned_end_date', 'progress_percentage',
    'project_status', 'attributes', 'source_id', 'import_job_id', 'source_original_data',
    'publication_status', 'created_by', 'reviewed_by', 'published_at',
])]
class VersiProyek extends Model
{
    use HasUuids;

    protected $table = 'project_versions';

    /** @param Builder<VersiProyek> $query */
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
            'start_date' => 'date',
            'planned_end_date' => 'date',
            'progress_percentage' => 'decimal:2',
            'attributes' => 'array',
            'source_original_data' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Proyek, $this> */
    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class, 'project_id');
    }

    /** @return BelongsTo<Kategori, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'category_id');
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

    /** @return BelongsToMany<Wilayah, $this, CakupanWilayahProyek> */
    public function wilayahCakupan(): BelongsToMany
    {
        return $this->belongsToMany(Wilayah::class, 'project_version_regions', 'project_version_id', 'region_id')
            ->using(CakupanWilayahProyek::class)
            ->withPivot(['id', 'is_primary'])
            ->withTimestamps();
    }
}
