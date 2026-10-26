<?php

namespace App\Models;

use App\Models\Apbd\Apbd;
use App\Models\Dataset\Dataset;
use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Feedback\RiwayatUmpanBalik;
use App\Models\Feedback\UmpanBalik;
use App\Models\Wilayah\VersiWilayah;
use Database\Factories\PenggunaFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property string $account_status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Pengguna extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<PenggunaFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'users';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<LogAudit, $this> */
    public function logAudit(): HasMany
    {
        return $this->hasMany(LogAudit::class, 'actor_user_id');
    }

    /** @return HasMany<PekerjaanImpor, $this> */
    public function pekerjaanImpor(): HasMany
    {
        return $this->hasMany(PekerjaanImpor::class, 'initiated_by');
    }

    /** @return HasMany<VersiWilayah, $this> */
    public function versiWilayahDibuat(): HasMany
    {
        return $this->hasMany(VersiWilayah::class, 'created_by');
    }

    /** @return HasMany<VersiWilayah, $this> */
    public function versiWilayahDitinjau(): HasMany
    {
        return $this->hasMany(VersiWilayah::class, 'reviewed_by');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitasDibuat(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'created_by');
    }

    /** @return HasMany<VersiFasilitas, $this> */
    public function versiFasilitasDitinjau(): HasMany
    {
        return $this->hasMany(VersiFasilitas::class, 'reviewed_by');
    }

    /** @return HasMany<Apbd, $this> */
    public function apbdDibuat(): HasMany
    {
        return $this->hasMany(Apbd::class, 'created_by');
    }

    /** @return HasMany<Apbd, $this> */
    public function apbdDitinjau(): HasMany
    {
        return $this->hasMany(Apbd::class, 'reviewed_by');
    }

    /** @return HasMany<Dataset, $this> */
    public function datasetDibuat(): HasMany
    {
        return $this->hasMany(Dataset::class, 'created_by');
    }

    /** @return HasMany<VersiDataset, $this> */
    public function versiDatasetDibuat(): HasMany
    {
        return $this->hasMany(VersiDataset::class, 'created_by');
    }

    /** @return HasMany<VersiDataset, $this> */
    public function versiDatasetDitinjau(): HasMany
    {
        return $this->hasMany(VersiDataset::class, 'reviewed_by');
    }

    /** @return HasMany<UmpanBalik, $this> */
    public function umpanBalik(): HasMany
    {
        return $this->hasMany(UmpanBalik::class, 'user_id');
    }

    /** @return HasMany<RiwayatUmpanBalik, $this> */
    public function riwayatUmpanBalik(): HasMany
    {
        return $this->hasMany(RiwayatUmpanBalik::class, 'actor_user_id');
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
