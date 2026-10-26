<?php

namespace App\Models\Apbd;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Feedback\UmpanBalik;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\SumberData;
use Database\Factories\Apbd\ApbdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $periode_data
 * @property Carbon|string $tanggal
 * @property string $kode_opd
 * @property string $nama_opd
 * @property string $akun_jenis
 * @property string $nama_akun
 * @property string $anggaran
 * @property string $realisasi
 * @property string $source_fingerprint
 * @property string|null $revises_id
 * @property string|null $source_id
 * @property string|null $import_job_id
 * @property array<string, mixed>|null $source_original_data
 * @property PublicationStatus $publication_status
 * @property string $created_by
 * @property string|null $reviewed_by
 * @property Carbon|null $published_at
 */
#[Fillable([
    'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
    'source_fingerprint', 'revises_id', 'source_id', 'import_job_id', 'source_original_data',
    'publication_status', 'created_by', 'reviewed_by', 'published_at',
])]

class Apbd extends Model
{
    /** @use HasFactory<ApbdFactory> */
    use HasFactory, HasUuids;

    protected $table = 'apbd';

    /** @param Builder<Apbd> $query */
    #[Scope]
    protected function terpublikasi(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::Published->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'source_original_data' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
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

    /** @return BelongsTo<self, $this> */
    public function revisiDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revises_id');
    }

    /** @return HasMany<self, $this> */
    public function revisi(): HasMany
    {
        return $this->hasMany(self::class, 'revises_id');
    }

    /** @return HasMany<UmpanBalik, $this> */
    public function umpanBalik(): HasMany
    {
        return $this->hasMany(UmpanBalik::class, 'apbd_id');
    }
}
