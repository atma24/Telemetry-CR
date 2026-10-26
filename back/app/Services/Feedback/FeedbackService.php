<?php

namespace App\Services\Feedback;

use App\Models\Feedback\RiwayatUmpanBalik;
use App\Models\Feedback\UmpanBalik;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FeedbackService
{
    public function submit(
        string $subject,
        string $message,
        ?string $facilityId,
        ?string $apbdId,
        ?string $regionId,
        ?string $datasetId,
        string $userId,
    ): UmpanBalik {
        return DB::transaction(function () use ($subject, $message, $facilityId, $apbdId, $regionId, $datasetId, $userId): UmpanBalik {
            $feedback = UmpanBalik::query()->create([
                'user_id' => $userId,
                'facility_id' => $facilityId,
                'apbd_id' => $apbdId,
                'region_id' => $regionId,
                'dataset_id' => $datasetId,
                'subject' => $subject,
                'message' => $message,
                'status' => 'submitted',
            ]);

            RiwayatUmpanBalik::query()->create([
                'feedback_id' => $feedback->id,
                'actor_user_id' => $userId,
                'old_status' => null,
                'new_status' => 'submitted',
            ]);

            return $feedback;
        });
    }

    /** @return LengthAwarePaginator<int, UmpanBalik> */
    public function adminIndex(int $perPage = 15): LengthAwarePaginator
    {
        return UmpanBalik::query()
            ->with(['pengguna', 'fasilitas', 'apbd', 'wilayah', 'dataset', 'riwayat'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function updateStatus(
        string $feedbackId,
        string $status,
        ?string $responseMessage,
        ?string $rejectionReason,
        string $actorId,
    ): UmpanBalik {
        return DB::transaction(function () use ($feedbackId, $status, $responseMessage, $rejectionReason, $actorId): UmpanBalik {
            $feedback = UmpanBalik::query()->whereKey($feedbackId)->lockForUpdate()->firstOrFail();
            $oldStatus = $feedback->status;
            $feedback->forceFill([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            ])->save();

            RiwayatUmpanBalik::query()->create([
                'feedback_id' => $feedbackId,
                'actor_user_id' => $actorId,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'response_message' => $responseMessage,
            ]);

            return $feedback->refresh();
        });
    }
}
