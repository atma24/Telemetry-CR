<?php

use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Wilayah\RegionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('queues administrative boundary GeoJSON and creates region drafts with original geometry only', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $geoJson = json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[106.7, -6.3], [106.9, -6.3], [106.9, -6.1], [106.7, -6.3]]]],
            'properties' => ['kode_wilayah' => '31', 'nama_wilayah' => 'DKI Jakarta', 'tingkat_wilayah' => 'provinsi'],
        ]],
    ], JSON_THROW_ON_ERROR);

    $response = $this->actingAs($admin)->post('/api/admin/regions/import', [
        'file' => UploadedFile::fake()->createWithContent('regions.geojson', $geoJson),
    ], ['Accept' => 'application/json']);
    $response->assertAccepted();
    $job = PekerjaanImpor::query()->firstOrFail();
    app(RegionImportService::class)->process($job->id);

    $version = VersiWilayah::query()->firstOrFail();
    expect($version->code)->toBe('31')
        ->and($version->geometry['type'])->toBe('Polygon')
        ->and($version->centroid_latitude)->toBeNull()
        ->and($job->fresh()->accepted_rows)->toBe(1)
        ->and($job->fresh()->warning_rows)->toBe(1);
});
