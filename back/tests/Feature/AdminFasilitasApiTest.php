<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Wilayah\Wilayah;
use Illuminate\Support\Str;

function makePublishedRegionForFacilityAdmin(Pengguna $admin, string $name = 'DKI Jakarta'): Wilayah
{
    $region = Wilayah::query()->create();
    $region->versi()->create([
        'version_no' => 1,
        'code' => '31',
        'name' => $name,
        'level' => 'provinsi',
        'publication_status' => PublicationStatus::Published,
        'created_by' => $admin->id,
        'published_at' => now(),
    ]);

    return $region;
}

function makeFacilityCategoryForAdminTest(): Kategori
{
    return Kategori::query()->create([
        'name' => 'Fasilitas Publik',
        'slug' => 'fasilitas-publik-admin-test',
    ]);
}

/** @return array<string, mixed> */
function facilityAdminPayload(Kategori $category, Wilayah $region): array
{
    return [
        'name' => 'Perpustakaan Kota',
        'category_id' => $category->id,
        'region_id' => $region->id,
        'address' => 'Jalan Contoh No. 1',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'attributes' => [
            'accessibility' => [
                'ramp' => true,
                'lift' => false,
                'accessible_toilet' => null,
                'accessible_parking' => true,
            ],
        ],
        'source_original_data' => ['entry_origin' => 'admin_manual'],
        'publication_status' => 'published',
        'created_by' => '00000000-0000-7000-8000-000000000000',
    ];
}

it('returns 401 to guests and 403 to citizens requesting admin facilities', function () {
    $this->getJson('/api/admin/facilities')->assertUnauthorized();

    $citizen = Pengguna::factory()->create();

    $this->actingAs($citizen)
        ->getJson('/api/admin/facilities')
        ->assertForbidden();
});

it('lets an admin create a draft and inspect facility versions and publication status', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $region = makePublishedRegionForFacilityAdmin($admin);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin/facilities', facilityAdminPayload($category, $region))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Perpustakaan Kota')
        ->assertJsonPath('data.version_no', 1)
        ->assertJsonPath('data.publication_status', 'draft')
        ->assertJsonPath('data.attributes.accessibility.ramp', true)
        ->assertJsonPath('data.created_by', $admin->id);

    $facilityId = $response->json('data.facility_id');
    $versionId = $response->json('data.id');

    $this->getJson('/api/admin/facilities?publication_status=draft&category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $versionId)
        ->assertJsonPath('data.0.facility_id', $facilityId)
        ->assertJsonPath('data.0.category.name', 'Fasilitas Publik');

    $this->getJson("/api/admin/facilities/{$facilityId}")
        ->assertOk()
        ->assertJsonPath('data.id', $facilityId)
        ->assertJsonPath('data.versions.0.id', $versionId)
        ->assertJsonPath('data.versions.0.publication_status', 'draft')
        ->assertJsonPath('data.versions.0.region.name', 'DKI Jakarta')
        ->assertJsonPath('data.versions.0.source_original_data.entry_origin', 'admin_manual');

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'facility.created',
        'entity_id' => $facilityId,
    ]);
    $this->assertDatabaseCount('facilities', 1);
    $this->assertDatabaseCount('facility_versions', 1);
});

it('rejects invalid coordinates and accessibility values before persisting a facility', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $region = makePublishedRegionForFacilityAdmin($admin);
    $payload = facilityAdminPayload($category, $region);
    $payload['latitude'] = 90.0000001;
    $payload['attributes']['accessibility']['ramp'] = 'maybe';

    $this->actingAs($admin)
        ->postJson('/api/admin/facilities', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'attributes.accessibility.ramp']);

    $this->assertDatabaseCount('facilities', 0);
    $this->assertDatabaseCount('facility_versions', 0);
});

it('requires facility identity fields and rejects categories that do not exist', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $region = makePublishedRegionForFacilityAdmin($admin);

    $this->actingAs($admin)
        ->postJson('/api/admin/facilities', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'category_id']);

    $payload = facilityAdminPayload($category, $region);
    $payload['category_id'] = (string) Str::uuid7();

    $this->postJson('/api/admin/facilities', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);

    $this->assertDatabaseCount('facilities', 0);
});

it('requires coordinate pairs on drafts and a currently published region before validation', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $unpublishedRegion = Wilayah::query()->create();
    $payload = facilityAdminPayload($category, $unpublishedRegion);
    unset($payload['longitude']);

    $this->actingAs($admin)
        ->postJson('/api/admin/facilities', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['longitude']);

    $payload['longitude'] = 106.8;
    $draft = $this->postJson('/api/admin/facilities', $payload)->assertCreated();
    $this->postJson('/api/admin/facilities/'.$draft->json('data.facility_id').'/versions/'.$draft->json('data.id').'/validate')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['region_id']);

    $this->assertDatabaseCount('facilities', 1);
});

it('publishes replacement versions without losing history and exposes only the published public fields', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $region = makePublishedRegionForFacilityAdmin($admin);

    $draft = $this->actingAs($admin)
        ->postJson('/api/admin/facilities', facilityAdminPayload($category, $region))
        ->assertCreated();
    $facilityId = $draft->json('data.facility_id');
    $firstVersionId = $draft->json('data.id');

    $this->getJson('/api/facilities')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$firstVersionId}/validate")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'validating');
    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$firstVersionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'published');

    $this->getJson("/api/facilities/{$facilityId}")
        ->assertOk()
        ->assertJsonPath('id', $facilityId)
        ->assertJsonPath('version_id', $firstVersionId)
        ->assertJsonPath('name', 'Perpustakaan Kota')
        ->assertJsonMissingPath('source_original_data')
        ->assertJsonMissingPath('import_job_id')
        ->assertJsonMissingPath('created_by')
        ->assertJsonMissingPath('reviewed_by');

    $replacement = $this->putJson("/api/admin/facilities/{$facilityId}", ['name' => 'Perpustakaan DKI'])
        ->assertCreated()
        ->assertJsonPath('data.version_no', 2)
        ->assertJsonPath('data.publication_status', 'draft');
    $replacementVersionId = $replacement->json('data.id');

    $this->getJson("/api/facilities/{$facilityId}")
        ->assertOk()
        ->assertJsonPath('version_id', $firstVersionId)
        ->assertJsonPath('name', 'Perpustakaan Kota');

    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$replacementVersionId}/validate")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'validating');
    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$replacementVersionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'published');

    $this->assertDatabaseHas('facility_versions', [
        'id' => $firstVersionId,
        'publication_status' => 'superseded',
        'name' => 'Perpustakaan Kota',
    ]);
    $this->assertDatabaseHas('facility_versions', [
        'id' => $replacementVersionId,
        'publication_status' => 'published',
        'name' => 'Perpustakaan DKI',
    ]);
    $this->getJson("/api/facilities/{$facilityId}")
        ->assertOk()
        ->assertJsonPath('id', $facilityId)
        ->assertJsonPath('version_id', $replacementVersionId)
        ->assertJsonPath('name', 'Perpustakaan DKI');

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'facility.version_created',
        'entity_id' => $facilityId,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'facility.validated',
        'entity_id' => $facilityId,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'facility.published',
        'entity_id' => $facilityId,
    ]);
    $this->assertDatabaseCount('facility_versions', 2);
});

it('archives the current published version and hides it from the public catalog', function () {
    $admin = Pengguna::factory()->admin()->create();
    $category = makeFacilityCategoryForAdminTest();
    $region = makePublishedRegionForFacilityAdmin($admin);
    $draft = $this->actingAs($admin)
        ->postJson('/api/admin/facilities', facilityAdminPayload($category, $region))
        ->assertCreated();
    $facilityId = $draft->json('data.facility_id');
    $versionId = $draft->json('data.id');

    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$versionId}/validate")->assertOk();
    $this->postJson("/api/admin/facilities/{$facilityId}/versions/{$versionId}/publish")->assertOk();
    $this->postJson("/api/admin/facilities/{$facilityId}/archive")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'archived');

    $this->getJson('/api/facilities')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/facilities/{$facilityId}")->assertNotFound();
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'facility.archived',
        'entity_id' => $facilityId,
    ]);
    $this->assertDatabaseHas('facility_versions', [
        'id' => $versionId,
        'publication_status' => 'archived',
    ]);
});
