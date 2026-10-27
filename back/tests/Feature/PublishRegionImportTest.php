<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Wilayah\WilayahService;
use Illuminate\Support\Facades\Storage;

function jakartaRegionDocument(): array
{
    $ring = [[106.80, -6.20], [106.81, -6.20], [106.81, -6.19], [106.80, -6.20]];
    $feature = fn (string $code, string $name, string $level, ?string $parent): array => [
        'type' => 'Feature',
        'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
        'properties' => [
            'kode_wilayah' => $code,
            'nama_wilayah' => $name,
            'tingkat_wilayah' => $level,
            'kode_wilayah_induk' => $parent,
        ],
    ];

    return [
        'type' => 'FeatureCollection',
        'features' => [
            $feature('31', 'DKI JAKARTA', 'provinsi', null),
            $feature('3171', 'JAKARTA PUSAT', 'kota', '31'),
            $feature('317101', 'GAMBIR', 'kecamatan', '3171'),
            $feature('3171011001', 'GAMBIR', 'kelurahan', '317101'),
        ],
    ];
}

it('plans every hierarchy level in a dry run without writing region data', function () {
    Storage::fake('local');
    Storage::disk('local')->put('regions/test.geojson', json_encode(jakartaRegionDocument(), JSON_THROW_ON_ERROR));

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(VersiWilayah::query()->count())->toBe(0);
});

it('validates and publishes regions from province to village and is safe to rerun', function () {
    Storage::fake('local');
    Storage::disk('local')->put('regions/test.geojson', json_encode(jakartaRegionDocument(), JSON_THROW_ON_ERROR));
    $admin = Pengguna::factory()->admin()->create();

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--actor' => $admin->email,
    ])->assertSuccessful();

    $versions = VersiWilayah::query()->get()->keyBy('code');

    expect($versions)->toHaveCount(4)
        ->and($versions['31']->publication_status)->toBe(PublicationStatus::Published)
        ->and($versions['3171']->publication_status)->toBe(PublicationStatus::Published)
        ->and($versions['3171']->parent_region_id)->toBe($versions['31']->region_id)
        ->and($versions['317101']->parent_region_id)->toBe($versions['3171']->region_id)
        ->and($versions['3171011001']->parent_region_id)->toBe($versions['317101']->region_id)
        ->and(LogAudit::query()->where('action', 'region.published')->count())->toBe(4);

    $regionIds = $versions->pluck('region_id', 'code');

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--actor' => $admin->email,
    ])->assertSuccessful();

    expect(VersiWilayah::query()->count())->toBe(4)
        ->and(VersiWilayah::query()->get()->pluck('region_id', 'code')->all())->toBe($regionIds->all());
});

it('requires an active admin actor before publishing regions', function () {
    Storage::fake('local');
    Storage::disk('local')->put('regions/test.geojson', json_encode(jakartaRegionDocument(), JSON_THROW_ON_ERROR));

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--actor' => 'missing@example.test',
    ])->assertFailed();

    expect(VersiWilayah::query()->count())->toBe(0);
});

it('publishes an existing draft instead of creating a second region version', function () {
    Storage::fake('local');
    Storage::disk('local')->put('regions/test.geojson', json_encode(jakartaRegionDocument(), JSON_THROW_ON_ERROR));
    $admin = Pengguna::factory()->admin()->create();
    $draft = app(WilayahService::class)->createDraft([
        'code' => '31',
        'name' => 'DKI JAKARTA',
        'level' => 'provinsi',
        'parent_region_id' => null,
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[106.80, -6.20], [106.81, -6.20], [106.81, -6.19], [106.80, -6.20]]]],
    ], $admin);

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--actor' => $admin->email,
    ])->assertSuccessful();

    expect(VersiWilayah::query()->count())->toBe(4)
        ->and($draft->fresh()->publication_status)->toBe(PublicationStatus::Published)
        ->and($draft->fresh()->region_id)->toBe($draft->region_id);
});

it('publishes an existing validating region version', function () {
    Storage::fake('local');
    Storage::disk('local')->put('regions/test.geojson', json_encode(jakartaRegionDocument(), JSON_THROW_ON_ERROR));
    $admin = Pengguna::factory()->admin()->create();
    $draft = app(WilayahService::class)->createDraft([
        'code' => '31',
        'name' => 'DKI JAKARTA',
        'level' => 'provinsi',
        'parent_region_id' => null,
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[106.80, -6.20], [106.81, -6.20], [106.81, -6.19], [106.80, -6.20]]]],
    ], $admin);
    app(WilayahService::class)->validateVersion($draft->region_id, $draft->id, $admin);

    $this->artisan('app:publish-region-import', [
        '--file' => 'regions/test.geojson',
        '--actor' => $admin->email,
    ])->assertSuccessful();

    expect($draft->fresh()->publication_status)->toBe(PublicationStatus::Published)
        ->and(VersiWilayah::query()->where('code', '31')->count())->toBe(1);
});
