<?php

namespace App\Services\Audit;

use App\Models\LogAudit;
use App\Models\Pengguna;

class AuditLogService
{
    /** @param array<string, mixed>|null $oldData
     * @param  array<string, mixed>|null  $newData
     */
    public function record(
        Pengguna $actor,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldData = null,
        ?array $newData = null,
    ): LogAudit {
        return LogAudit::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
