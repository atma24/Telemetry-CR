<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('requires authentication to submit feedback', function () {
    $this->postJson('/api/feedback', [
        'subject' => 'Suggestion',
        'message' => 'Please add more data.',
    ])->assertUnauthorized();
});

it('submits feedback and records its initial status event', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/feedback', [
        'subject' => 'Suggestion',
        'message' => 'Please add more data.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('subject', 'Suggestion')
        ->assertJsonPath('status', 'submitted');

    $this->assertDatabaseHas('feedback_events', [
        'feedback_id' => $response->json('id'),
        'actor_user_id' => $user->id,
        'old_status' => null,
        'new_status' => 'submitted',
    ]);
});

it('rejects feedback that references more than one target', function () {
    $user = User::factory()->create();
    $facilityId = (string) Str::uuid7();
    $projectId = (string) Str::uuid7();
    DB::table('facilities')->insert(['id' => $facilityId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('projects')->insert(['id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)->postJson('/api/feedback', [
        'facility_id' => $facilityId,
        'project_id' => $projectId,
        'subject' => 'Invalid target',
        'message' => 'This points to more than one record.',
    ])->assertUnprocessable()->assertJsonValidationErrors('facility_id');
});

it('lets an administrator update status while recording the status transition', function () {
    $admin = User::factory()->admin()->create();
    $feedbackId = (string) Str::uuid7();
    $citizen = User::factory()->create();

    DB::table('feedbacks')->insert([
        'id' => $feedbackId,
        'user_id' => $citizen->id,
        'subject' => 'Incorrect information',
        'message' => 'Please review this record.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->patchJson("/api/admin/feedback/{$feedbackId}/status", [
        'status' => 'resolved',
        'response_message' => 'The information has been reviewed.',
    ])->assertOk()->assertJsonPath('status', 'resolved');

    $this->assertDatabaseHas('feedback_events', [
        'feedback_id' => $feedbackId,
        'actor_user_id' => $admin->id,
        'old_status' => 'submitted',
        'new_status' => 'resolved',
        'response_message' => 'The information has been reviewed.',
    ]);
});
