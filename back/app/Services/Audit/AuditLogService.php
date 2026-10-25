<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /** @param array<string, mixed>|null $oldData
     * @param  array<string, mixed>|null  $newData
     */
    public function record(
        User $actor,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldData = null,
        ?array $newData = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
