<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Kategori;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\Wilayah\Wilayah;
use App\Services\Fasilitas\FacilityImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('queues a school CSV regardless of header case or column order and imports blank coordinates as warnings', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $category = Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $csv = "NAMA SEKOLAH, npsn, PERIODE DATA, alamat, kelurahan, kecamatan, latitude, longitude, jumlah_siswa, jumlah_guru, kepala_sekolah, telp_sekolah, akreditasi\n"
        .'SD Negeri 1,12345678,2017,"Jalan A",,Kecamatan A,,,100,10,,,A';

    $response = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'school',
        'file' => UploadedFile::fake()->createWithContent('schools.csv', $csv),
    ], ['Accept' => 'application/json']);

    $response->assertAccepted()->assertJsonPath('data.dataset_type', 'facility_school');
    $job = PekerjaanImpor::query()->firstOrFail();
    app(FacilityImportService::class)->process($job->id);
    app(FacilityImportService::class)->process($job->id);

    $version = VersiFasilitas::query()->with('sekolah')->firstOrFail();
    expect($category->id)->toBe($version->category_id)
        ->and($version->latitude)->toBeNull()
        ->and($version->longitude)->toBeNull()
        ->and($version->sekolah->periode_data)->toBe('2017')
        ->and($version->sekolah->jumlah_siswa)->toBe(100)
        ->and($job->fresh()->accepted_rows)->toBe(1)
        ->and($job->fresh()->warning_rows)->toBe(1);

    $laterPeriodResponse = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'school',
        'file' => UploadedFile::fake()->createWithContent('schools-later.csv', "nama_sekolah,npsn,periode_data\nSD Negeri 1,12345678,2018"),
    ], ['Accept' => 'application/json']);
    $laterPeriodResponse->assertAccepted();
    $laterJob = PekerjaanImpor::query()->findOrFail($laterPeriodResponse->json('data.id'));
    app(FacilityImportService::class)->process($laterJob->id);

    expect(VersiFasilitas::query()->count())->toBe(2)
        ->and(VersiFasilitas::query()->distinct('facility_id')->count('facility_id'))->toBe(1)
        ->and(VersiFasilitas::query()->with('sekolah')->get()->pluck('sekolah.periode_data')->all())->toContain('2017', '2018');

    $retry = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'school',
        'file' => UploadedFile::fake()->createWithContent('schools-retry.csv', "nama_sekolah,npsn,periode_data\nSD Negeri 1,12345678,2018"),
    ], ['Accept' => 'application/json']);
    $retry->assertAccepted();
    $retryJob = PekerjaanImpor::query()->findOrFail($retry->json('data.id'));
    app(FacilityImportService::class)->process($retryJob->id);

    expect(VersiFasilitas::query()->count())->toBe(2)
        ->and($retryJob->fresh()->rejected_rows)->toBe(1);
});

it('fails a school import with unknown or missing required headers and creates no partial records', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $response = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'school',
        'periode_data' => '2017',
        'file' => UploadedFile::fake()->createWithContent('bad.csv', "nama_sekolah,unknown_column\nSekolah A,value"),
    ], ['Accept' => 'application/json']);
    $response->assertAccepted();

    $job = PekerjaanImpor::query()->firstOrFail();
    app(FacilityImportService::class)->process($job->id);

    expect($job->fresh()->status)->toBe('failed')
        ->and($job->fresh()->failure_reason)->toContain('Unknown columns')
        ->and(VersiFasilitas::query()->count())->toBe(0);
});

it('reads HTML-table XLS market uploads and maps negative X to latitude without collapsing periods', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    Kategori::query()->create(['name' => 'Pasar', 'slug' => 'pasar']);
    $html = '<html><table><tr><th>periode_data</th><th>nama_pasar</th><th>koordinat_x</th><th>koordinat_y</th><th>alamat</th><th>wilayah</th><th>kecamatan</th><th>kelurahan</th><th>kode_pos</th><th>telepon</th></tr>'
        .'<tr><td>2024</td><td>Pasar Contoh</td><td>-6.1874067</td><td>106.8141357</td><td>Jalan B</td><td>Jakarta</td><td>Kecamatan B</td><td>Kelurahan B</td><td></td><td></td></tr>'
        .'<tr><td>2025</td><td>Pasar Contoh</td><td>-6.1874067</td><td>106.8141357</td><td>Jalan B</td><td>Jakarta</td><td>Kecamatan B</td><td>Kelurahan B</td><td></td><td></td></tr></table></html>';

    $response = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'market',
        'file' => UploadedFile::fake()->createWithContent('markets.xls', $html),
    ], ['Accept' => 'application/json']);
    $response->assertAccepted();
    $job = PekerjaanImpor::query()->firstOrFail();
    app(FacilityImportService::class)->process($job->id);

    $versions = VersiFasilitas::query()->with('lokasiPasar')->orderBy('id')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions->pluck('latitude')->unique()->all())->toBe(['-6.1874067'])
        ->and($versions->pluck('longitude')->unique()->all())->toBe(['106.8141357'])
        ->and($versions->pluck('lokasiPasar.periode_data')->all())->toContain('2024', '2025')
        ->and($job->fresh()->accepted_rows)->toBe(2);
});

it('rejects non-point or invalid OSM coordinates as row errors while retaining null accessibility as unknown', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    Kategori::query()->create(['name' => 'Fasilitas Publik', 'slug' => 'fasilitas-publik']);
    $geoJson = json_encode([
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [106.81, -6.18]],
                'properties' => ['@id' => 'node/1', 'name' => 'Fasilitas OSM'],
            ],
            [
                'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [999, -6.18]],
                'properties' => ['@id' => 'node/2', 'name' => 'Koordinat tidak valid'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'osm',
        'file' => UploadedFile::fake()->createWithContent('osm.geojson', $geoJson),
    ], ['Accept' => 'application/json']);
    $response->assertAccepted();
    $job = PekerjaanImpor::query()->firstOrFail();
    app(FacilityImportService::class)->process($job->id);

    $version = VersiFasilitas::query()->firstOrFail();
    expect($version->fasilitas->external_key)->toBe('osm:node/1')
        ->and($version->latitude)->toBe('-6.1800000')
        ->and($version->longitude)->toBe('106.8100000')
        ->and($version->attributes['accessibility']['wheelchair'])->toBeNull()
        ->and($version->region_id)->toBeNull()
        ->and($job->fresh()->accepted_rows)->toBe(1)
        ->and($job->fresh()->rejected_rows)->toBe(1);
});

it('uses only explicit OSM names and point geometry, handles feature ids, malformed JSON, and repeat imports safely', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    Kategori::query()->create(['name' => 'Fasilitas Publik', 'slug' => 'fasilitas-publik']);
    $geoJson = json_encode([
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature', 'id' => 'way/42',
                'geometry' => ['type' => 'Point', 'coordinates' => [106.81, -6.18]],
                'properties' => ['name' => 'Taman Contoh', 'amenity' => 'park'],
            ],
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [106.82, -6.19]],
                'properties' => ['@id' => 'node/43', 'operator' => 'Operator bukan nama', 'brand' => 'Brand bukan nama'],
            ],
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'MultiPoint', 'coordinates' => [[106.83, -6.20]]],
                'properties' => ['@id' => 'node/44', 'name' => 'Bukan Point'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $submit = fn (string $filename, string $contents) => $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'osm',
        'file' => UploadedFile::fake()->createWithContent($filename, $contents),
    ], ['Accept' => 'application/json']);

    $firstResponse = $submit('osm.geojson', $geoJson);
    $firstResponse->assertAccepted();
    $firstJob = PekerjaanImpor::query()->findOrFail($firstResponse->json('data.id'));
    app(FacilityImportService::class)->process($firstJob->id);
    $beforeRetry = VersiFasilitas::query()->count();

    $retryResponse = $submit('osm-retry.geojson', $geoJson);
    $retryResponse->assertAccepted();
    $retryJob = PekerjaanImpor::query()->findOrFail($retryResponse->json('data.id'));
    app(FacilityImportService::class)->process($retryJob->id);

    expect($beforeRetry)->toBe(1)
        ->and(VersiFasilitas::query()->count())->toBe(1)
        ->and(VersiFasilitas::query()->firstOrFail()->fasilitas->external_key)->toBe('osm:way/42')
        ->and($firstJob->fresh()->accepted_rows)->toBe(1)
        ->and($firstJob->fresh()->rejected_rows)->toBe(2)
        ->and($retryJob->fresh()->accepted_rows)->toBe(0)
        ->and($retryJob->fresh()->rejected_rows)->toBe(3);

    $malformedResponse = $submit('broken.geojson', '{not-json');
    $malformedResponse->assertAccepted();
    $malformedJob = PekerjaanImpor::query()->findOrFail($malformedResponse->json('data.id'));
    app(FacilityImportService::class)->process($malformedJob->id);

    expect($malformedJob->fresh()->status)->toBe('failed')
        ->and(VersiFasilitas::query()->count())->toBe(1);
});

it('matches facility localities only by an exact published village and parent district pair', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $district = Wilayah::query()->create();
    $district->versi()->create([
        'version_no' => 1, 'code' => '317101', 'name' => 'Kecamatan X', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);
    $village = Wilayah::query()->create();
    $village->versi()->create([
        'version_no' => 1, 'code' => '3171011001', 'name' => 'Gambir', 'level' => 'kelurahan',
        'parent_region_id' => $district->id, 'publication_status' => PublicationStatus::Published,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post('/api/admin/facilities/import', [
        'dataset_type' => 'school',
        'periode_data' => '2024',
        'file' => UploadedFile::fake()->createWithContent(
            'schools.csv',
            "nama_sekolah,npsn,kelurahan,kecamatan\nSekolah Cocok,12345678,Gambir,Kecamatan X\nSekolah Tidak Cocok,87654321,Gambir,"
        ),
    ], ['Accept' => 'application/json']);
    $response->assertAccepted();
    $job = PekerjaanImpor::query()->findOrFail($response->json('data.id'));
    app(FacilityImportService::class)->process($job->id);

    $versions = VersiFasilitas::query()->orderBy('name')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions->firstWhere('name', 'Sekolah Cocok')->region_id)->toBe($village->id)
        ->and($versions->firstWhere('name', 'Sekolah Tidak Cocok')->region_id)->toBeNull()
        ->and($job->fresh()->warning_rows)->toBe(2);
});
