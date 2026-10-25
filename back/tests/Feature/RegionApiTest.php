<?php

use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createRegionApiVersion(Pengguna $user, string $name, string $status, ?string $parentId = null): string
{
    $regionId = Str::uuid7()->toString();
    $now = now();

    DB::table('regions')->insert([
        'id' => $regionId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('region_versions')->insert([
        'id' => Str::uuid7()->toString(),
        'region_id' => $regionId,
        'version_no' => 1,
        'code' => '31.71.'.Str::random(5),
        'name' => $name,
        'level' => 'kecamatan',
        'parent_region_id' => $parentId,
        'publication_status' => $status,
        'created_by' => $user->id,
        'published_at' => $status === 'published' ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $regionId;
}

it('exposes only published regions to public visitors', function () {
    $user = Pengguna::factory()->create();
    $publishedRegionId = createRegionApiVersion($user, 'Kebayoran Baru', 'published');
    createRegionApiVersion($user, 'Draft Region', 'draft');

    $this->getJson('/api/regions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $publishedRegionId)
        ->assertJsonPath('data.0.name', 'Kebayoran Baru');
});

it('returns a published region by its stable identity id', function () {
    $user = Pengguna::factory()->create();
    $regionId = createRegionApiVersion($user, 'Menteng', 'published');

    $this->getJson('/api/regions/'.$regionId)
        ->assertOk()
        ->assertJsonPath('data.id', $regionId)
        ->assertJsonPath('data.name', 'Menteng');
});

it('does not expose a region that has no published version', function () {
    $user = Pengguna::factory()->create();
    $regionId = createRegionApiVersion($user, 'Unpublished Region', 'draft');

    $this->getJson('/api/regions/'.$regionId)->assertNotFound();
});

it('filters published regions and validates pagination input', function () {
    $user = Pengguna::factory()->create();
    createRegionApiVersion($user, 'Kebayoran Baru', 'published');
    createRegionApiVersion($user, 'Menteng', 'published');

    $this->getJson('/api/regions?search=Kebayoran&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Kebayoran Baru');

    $this->getJson('/api/regions?per_page=101')->assertUnprocessable();
});

it('returns the versioned API health status', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});
