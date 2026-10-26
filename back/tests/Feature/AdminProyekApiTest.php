<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Proyek\Proyek;
use App\Models\Wilayah\Wilayah;
use Illuminate\Support\Str;

function makePublishedRegionForProjectAdmin(Pengguna $admin, string $name): Wilayah
{
    $region = Wilayah::query()->create();
    $region->versi()->create([
        'version_no' => 1, 'code' => Str::upper(Str::random(5)), 'name' => $name, 'level' => 'provinsi',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id, 'published_at' => now(),
    ]);

    return $region;
}

function projectAdminCategory(): Kategori
{
    return Kategori::query()->create(['name' => 'Infrastruktur', 'slug' => 'infrastruktur-admin-project-test']);
}

/** @return array<string, mixed> */
function projectAdminPayload(Kategori $category, Wilayah $region): array
{
    return [
        'name' => 'Jalan Lingkar', 'category_id' => $category->id, 'agency' => 'Dinas PUPR',
        'start_date' => '2026-01-01', 'planned_end_date' => '2027-01-01', 'progress_percentage' => 25,
        'project_status' => 'berjalan', 'attributes' => ['phase' => 'one'],
        'regions' => [['region_id' => $region->id, 'is_primary' => true]],
        'publication_status' => 'published', 'created_by' => (string) Str::uuid7(), 'reviewed_by' => (string) Str::uuid7(),
    ];
}

it('protects admin project endpoints from guests and regular users', function () {
    $this->getJson('/api/admin/projects')->assertUnauthorized();
    $this->actingAs(Pengguna::factory()->create())->getJson('/api/admin/projects')->assertForbidden();
});

it('creates project drafts, preserves version-scoped regions, publishes replacements, and keeps public data safe', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = projectAdminCategory();
    $region = makePublishedRegionForProjectAdmin($admin, 'Jawa Barat');
    $draft = $this->actingAs($admin)->postJson('/api/admin/projects', projectAdminPayload($category, $region))
        ->assertCreated()->assertJsonPath('data.publication_status', 'draft')->assertJsonPath('data.created_by', $admin->id);
    $projectId = $draft->json('data.project_id');
    $v1 = $draft->json('data.id');
    $this->assertDatabaseHas('project_version_regions', ['project_version_id' => $v1, 'region_id' => $region->id, 'is_primary' => true]);
    $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');

    $this->postJson("/api/admin/projects/{$projectId}/versions/{$v1}/validate")->assertOk();
    $this->postJson("/api/admin/projects/{$projectId}/versions/{$v1}/publish")->assertOk();
    $public = $this->getJson("/api/projects/{$projectId}")->assertOk()
        ->assertJsonPath('name', 'Jalan Lingkar')->assertJsonPath('regions.0.name', 'Jawa Barat')
        ->assertJsonPath('regions.0.is_primary', true);
    foreach (['source_original_data', 'import_job_id', 'created_by', 'reviewed_by'] as $hidden) {
        $public->assertJsonMissingPath($hidden);
    }

    $v2 = $this->putJson("/api/admin/projects/{$projectId}", ['name' => 'Jalan Lingkar Baru'])
        ->assertCreated()->assertJsonPath('data.version_no', 2)->assertJsonPath('data.regions.0.id', $region->id);
    $v2id = $v2->json('data.id');
    $this->assertDatabaseHas('project_version_regions', ['project_version_id' => $v1, 'region_id' => $region->id, 'is_primary' => true]);
    $this->assertDatabaseHas('project_version_regions', ['project_version_id' => $v2id, 'region_id' => $region->id, 'is_primary' => true]);
    $this->postJson("/api/admin/projects/{$projectId}/versions/{$v2id}/validate")->assertOk();
    $this->postJson("/api/admin/projects/{$projectId}/versions/{$v2id}/publish")->assertOk();
    $this->assertDatabaseHas('project_versions', ['id' => $v1, 'publication_status' => 'superseded']);
    $this->getJson("/api/projects/{$projectId}")->assertOk()->assertJsonPath('version_id', $v2id)->assertJsonPath('name', 'Jalan Lingkar Baru');
    $this->getJson("/api/admin/projects/{$projectId}")->assertOk()->assertJsonCount(2, 'data.versions');
    $this->postJson("/api/admin/projects/{$projectId}/archive")->assertOk()->assertJsonPath('data.publication_status', 'archived');
    $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects invalid project values and regions without a published version', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = projectAdminCategory();
    $region = Wilayah::query()->create();
    $payload = projectAdminPayload($category, $region);
    $payload['progress_percentage'] = 101;
    $payload['planned_end_date'] = '2025-01-01';
    $payload['regions'][] = ['region_id' => $region->id, 'is_primary' => true];
    $this->actingAs($admin)->postJson('/api/admin/projects', $payload)->assertUnprocessable()
        ->assertJsonValidationErrors(['progress_percentage', 'planned_end_date', 'regions']);
    $payload = projectAdminPayload($category, $region);
    $this->postJson('/api/admin/projects', $payload)->assertUnprocessable()->assertJsonValidationErrors('regions');
    $this->assertDatabaseCount('projects', 0);
});

it('rejects publishing or validating a version belonging to another project', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = projectAdminCategory();
    $region = makePublishedRegionForProjectAdmin($admin, 'Banten');
    $this->actingAs($admin)->postJson('/api/admin/projects', projectAdminPayload($category, $region))->assertCreated();
    $project = Proyek::query()->firstOrFail();
    $this->postJson('/api/admin/projects/'.(string) Str::uuid7().'/versions/'.$project->versi()->value('id').'/validate')->assertNotFound();
});
