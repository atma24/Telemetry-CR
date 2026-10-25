<?php

namespace App\Models\Proyek;

use App\Models\Wilayah\Wilayah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CakupanWilayahProyek extends Pivot
{
    use HasUuids;

    protected $table = 'project_version_regions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['project_version_id', 'region_id', 'is_primary'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<VersiProyek, $this> */
    public function versiProyek(): BelongsTo
    {
        return $this->belongsTo(VersiProyek::class, 'project_version_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }
}
