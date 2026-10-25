<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('returns 401 when guests request admin regions', function () {
    $this->getJson('/api/admin/regions')->assertUnauthorized();
});

it('returns 403 when a citizen requests admin regions', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/regions')
        ->assertForbidden();
});

it('returns 403 when an inactive administrator requests admin regions', function () {
    $inactiveAdmin = User::factory()->admin()->create(['account_status' => 'suspended']);

    $this->actingAs($inactiveAdmin)
        ->getJson('/api/admin/regions')
        ->assertForbidden();
});

it('creates a draft without accepting server-controlled attributes', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/admin/regions', [
        'code' => '31',
        'name' => 'DKI Jakarta',
        'level' => 'provinsi',
        'publication_status' => 'published',
        'created_by' => '00000000-0000-7000-8000-000000000000',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'DKI Jakarta')
        ->assertJsonPath('data.version_no', 1)
        ->assertJsonPath('data.publication_status', 'draft');

    $this->assertDatabaseHas('region_versions', [
        'region_id' => $response->json('data.region_id'),
        'publication_status' => 'draft',
        'created_by' => $admin->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'region.created',
    ]);
});

it('rejects a child region whose published parent level is incompatible', function () {
    $admin = User::factory()->admin()->create();
    $parentId = (string) Str::uuid7();
    DB::table('regions')->insert(['id' => $parentId]);

    $this->actingAs($admin)->postJson('/api/admin/regions', [
        'code' => '31.01',
        'name' => 'Kabupaten',
        'level' => 'kabupaten',
        'parent_region_id' => $parentId,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('parent_region_id');
});

it('publishes a validated version and keeps the previous public version until publication', function () {
    $admin = User::factory()->admin()->create();
    $parentId = (string) Str::uuid7();
    DB::table('regions')->insert(['id' => $parentId]);
    DB::table('region_versions')->insert([
        'id' => (string) Str::uuid7(),
        'region_id' => $parentId,
        'version_no' => 1,
        'code' => '31',
        'name' => 'DKI Jakarta',
        'level' => 'provinsi',
        'publication_status' => 'published',
        'created_by' => $admin->id,
        'published_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $regionId = (string) Str::uuid7();
    $oldPublished = (string) Str::uuid7();
    $draftId = (string) Str::uuid7();
    DB::table('regions')->insert(['id' => $regionId]);
    DB::table('region_versions')->insert([
        'id' => $oldPublished,
        'region_id' => $regionId,
        'version_no' => 1,
        'code' => '31.71',
        'name' => 'Menteng',
        'level' => 'kota',
        'parent_region_id' => $parentId,
        'publication_status' => 'published',
        'created_by' => $admin->id,
        'published_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('region_versions')->insert([
        'id' => $draftId,
        'region_id' => $regionId,
        'version_no' => 2,
        'code' => '31.71',
        'name' => 'Menteng Baru',
        'level' => 'kota',
        'parent_region_id' => $parentId,
        'publication_status' => 'draft',
        'created_by' => $admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/regions/{$regionId}/versions/{$draftId}/validate")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'validating');

    $this->getJson("/api/regions/{$regionId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Menteng');

    $this->postJson("/api/admin/regions/{$regionId}/versions/{$draftId}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'published');

    $this->assertDatabaseHas('region_versions', [
        'id' => $oldPublished,
        'publication_status' => 'superseded',
    ]);
    $this->getJson("/api/regions/{$regionId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Menteng Baru');
});
