<?php

namespace App\Models\Feedback;

use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['feedback_id', 'actor_user_id', 'old_status', 'new_status', 'response_message'])]
class RiwayatUmpanBalik extends Model
{
    use HasUuids;

    protected $table = 'feedback_events';

    public const UPDATED_AT = null;

    /** @return BelongsTo<UmpanBalik, $this> */
    public function umpanBalik(): BelongsTo
    {
        return $this->belongsTo(UmpanBalik::class, 'feedback_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'actor_user_id');
    }
}
