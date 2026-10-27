<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('prepares normalized DKI Jakarta boundaries and composes the province from city polygons', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    $ring = fn (float $x, float $y): array => [[$x, $y], [$x + 0.01, $y], [$x + 0.01, $y + 0.01], [$x, $y]];
    $polygon = fn (float $x, float $y): array => ['type' => 'Polygon', 'coordinates' => [$ring($x, $y)]];
    $multiPolygon = fn (float $x, float $y): array => ['type' => 'MultiPolygon', 'coordinates' => [[$ring($x, $y)]]];

    $feature = fn (array $properties, array $geometry, string $type = 'Feature'): array => [
        'type' => $type,
        'geometry' => $geometry,
        'properties' => $properties,
    ];
    $collection = fn (array $features): array => [
        'type' => 'FeatureCollection',
        'features' => $features,
        'properties' => ['exceededTransferLimit' => false],
    ];

    Http::fake([
        '*batas_kota_kab*' => Http::response($collection([
            $feature(['kdpkab' => '3171', 'wadmkk' => 'JAKARTA PUSAT'], $polygon(106.80, -6.20)),
            $feature(['kdpkab' => '3172', 'wadmkk' => 'JAKARTA UTARA'], $multiPolygon(106.85, -6.15)),
        ])),
        '*Batas_Administrasi_Kecamatan_Jakarta*' => Http::response($collection([
            $feature(['kdcpum' => '317101', 'kdpkab' => '3171', 'wadmkc' => 'GAMBIR', 'wadmkk' => 'JAKARTA PUSAT'], $polygon(106.81, -6.19)),
            $feature(['kdcpum' => '3100', 'kdpkab' => '310', 'wadmkc' => 'PENJARINGAN', 'wadmkk' => 'JAKARTA UTARA'], $polygon(106.86, -6.14)),
        ])),
        '*Batas_Administrasi_Kelurahan_DKI_Jakarta*' => Http::response($collection([
            $feature(['kdepum' => '3171011001', 'kdcpum' => '317101', 'kdpkab' => '3171', 'wadmkd' => 'GAMBIR', 'wadmkc' => 'GAMBIR'], $polygon(106.815, -6.185)),
        ])),
    ]);

    $this->artisan('app:prepare-jakarta-regions', [
        '--output' => 'regions/test',
        '--allow-partial' => true,
    ])->assertSuccessful();

    $document = json_decode(
        Storage::disk('local')->get('regions/test/regions.geojson'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $manifest = json_decode(
        Storage::disk('local')->get('regions/test/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $features = collect($document['features']);
    $province = $features->firstWhere('properties.kode_wilayah', '31');

    expect($features)->toHaveCount(5)
        ->and($province['geometry']['type'])->toBe('MultiPolygon')
        ->and($province['geometry']['coordinates'])->toHaveCount(2)
        ->and($features->firstWhere('properties.kode_wilayah', '317101')['properties']['kode_wilayah_induk'])->toBe('3171')
        ->and($features->firstWhere('properties.kode_wilayah', '3171011001')['properties']['kode_wilayah_induk'])->toBe('317101')
        ->and($manifest['counts'])->toMatchArray([
            'provinsi' => 1,
            'kota' => 2,
            'kabupaten' => 0,
            'kecamatan' => 1,
            'kelurahan' => 1,
        ])
        ->and($manifest['excluded'])->toHaveCount(1)
        ->and($manifest['excluded'][0]['code'])->toBe('3100');
});

it('refuses to prepare a document when source counts are below the verified DKI Jakarta totals', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    $ring = [[106.80, -6.20], [106.81, -6.20], [106.81, -6.19], [106.80, -6.20]];
    $feature = fn (array $properties): array => [
        'type' => 'Feature',
        'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
        'properties' => $properties,
    ];

    Http::fake([
        '*batas_kota_kab*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [$feature(['kdpkab' => '3171', 'wadmkk' => 'JAKARTA PUSAT'])],
        ]),
        '*Batas_Administrasi_Kecamatan_Jakarta*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [$feature(['kdcpum' => '317101', 'kdpkab' => '3171', 'wadmkc' => 'GAMBIR'])],
        ]),
        '*Batas_Administrasi_Kelurahan_DKI_Jakarta*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [$feature(['kdepum' => '3171011001', 'kdcpum' => '317101', 'wadmkd' => 'GAMBIR'])],
        ]),
    ]);

    $this->artisan('app:prepare-jakarta-regions', ['--output' => 'regions/test'])->assertFailed();

    expect(Storage::disk('local')->exists('regions/test/regions.geojson'))->toBeFalse()
        ->and(Storage::disk('local')->exists('regions/test/manifest.json'))->toBeFalse();
});
