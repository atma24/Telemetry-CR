<?php

namespace App\Models\Wilayah;

use App\Enums\Wilayah\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'version_no', 'code', 'name', 'level', 'parent_region_id', 'source_id', 'import_job_id',
    'source_original_data', 'publication_status', 'created_by', 'reviewed_by', 'published_at',
])]
class RegionVersion extends Model
{
    use HasUuids;

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::Published->value);
    }

    protected function casts(): array
    {
        return [
            'source_original_data' => 'array',
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function parentRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_region_id');
    }
}
