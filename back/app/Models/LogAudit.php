<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_user_id', 'action', 'entity_type', 'entity_id', 'old_data', 'new_data'])]
class LogAudit extends Model
{
    use HasUuids;

    protected $table = 'audit_logs';

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'actor_user_id');
    }
}
