<?php

namespace App\Services\Feedback;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedbackService
{
    public function submit(
        string $subject,
        string $message,
        ?string $facilityId,
        ?string $projectId,
        ?string $regionId,
        ?string $datasetId,
        string $userId,
    ): object {
        $now = now();
        $feedbackId = (string) Str::uuid7();

        DB::transaction(function () use ($subject, $message, $facilityId, $projectId, $regionId, $datasetId, $feedbackId, $userId, $now): void {
            DB::table('feedbacks')->insert([
                'id' => $feedbackId,
                'user_id' => $userId,
                'facility_id' => $facilityId,
                'project_id' => $projectId,
                'region_id' => $regionId,
                'dataset_id' => $datasetId,
                'subject' => $subject,
                'message' => $message,
                'status' => 'submitted',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('feedback_events')->insert([
                'id' => (string) Str::uuid7(),
                'feedback_id' => $feedbackId,
                'actor_user_id' => $userId,
                'old_status' => null,
                'new_status' => 'submitted',
                'created_at' => $now,
            ]);
        });

        return DB::table('feedbacks')->where('id', $feedbackId)->firstOrFail();
    }

    public function adminIndex(int $perPage = 15): LengthAwarePaginator
    {
        return DB::table('feedbacks')->orderByDesc('created_at')->paginate(max(1, min($perPage, 100)));
    }

    public function updateStatus(
        string $feedbackId,
        string $status,
        ?string $responseMessage,
        ?string $rejectionReason,
        string $actorId,
    ): object {
        return DB::transaction(function () use ($feedbackId, $status, $responseMessage, $rejectionReason, $actorId): object {
            $feedback = DB::table('feedbacks')->where('id', $feedbackId)->lockForUpdate()->firstOrFail();
            $now = now();

            DB::table('feedbacks')->where('id', $feedbackId)->update([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                'updated_at' => $now,
            ]);

            DB::table('feedback_events')->insert([
                'id' => (string) Str::uuid7(),
                'feedback_id' => $feedbackId,
                'actor_user_id' => $actorId,
                'old_status' => $feedback->status,
                'new_status' => $status,
                'response_message' => $responseMessage,
                'created_at' => $now,
            ]);

            return DB::table('feedbacks')->where('id', $feedbackId)->firstOrFail();
        });
    }
}
