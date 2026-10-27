<?php

namespace App\Models\Fasilitas;

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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $facility_id
 * @property int $version_no
 * @property string $name
 * @property string $category_id
 * @property string $region_id
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property array<string, mixed>|null $attributes
 * @property string|null $source_id
 * @property string|null $import_job_id
 * @property array<string, mixed>|null $source_original_data
 * @property PublicationStatus $publication_status
 * @property string $created_by
 * @property string|null $reviewed_by
 * @property Carbon|null $published_at
 * @property-read Kategori|null $kategori
 * @property-read Wilayah|null $wilayah
 * @property-read SumberData|null $sumberData
 * @property-read Pengguna|null $pembuat
 * @property-read Pengguna|null $peninjau
 */
#[Fillable([
    'version_no', 'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude', 'attributes',
    'source_id', 'import_job_id', 'source_original_data', 'publication_status', 'created_by', 'reviewed_by', 'published_at',
])]
class VersiFasilitas extends Model
{
    use HasUuids;

    protected $table = 'facility_versions';

    /** @param Builder<VersiFasilitas> $query */
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
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attributes' => 'array',
            'source_original_data' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Fasilitas, $this> */
    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(Fasilitas::class, 'facility_id');
    }

    /** @return BelongsTo<Kategori, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'category_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
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

    /** @return HasOne<Sekolah, $this> */
    public function sekolah(): HasOne
    {
        return $this->hasOne(Sekolah::class, 'facility_version_id');
    }

    /** @return HasOne<LokasiPasar, $this> */
    public function lokasiPasar(): HasOne
    {
        return $this->hasOne(LokasiPasar::class, 'facility_version_id');
    }
}
