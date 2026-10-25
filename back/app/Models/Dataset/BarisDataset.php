<?php

namespace App\Models\Dataset;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dataset_version_id', 'row_number', 'row_data', 'validation_status', 'validation_errors'])]
class BarisDataset extends Model
{
    use HasUuids;

    protected $table = 'dataset_rows';

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'row_data' => 'array',
            'validation_errors' => 'array',
        ];
    }

    /** @return BelongsTo<VersiDataset, $this> */
    public function versiDataset(): BelongsTo
    {
        return $this->belongsTo(VersiDataset::class, 'dataset_version_id');
    }
}
