<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Kategori;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Wilayah\Wilayah;

function reconcileTestVillage(Pengguna $admin): Wilayah
{
    $district = Wilayah::query()->create();
    $district->versi()->create([
        'version_no' => 1, 'code' => '317201', 'name' => 'PENJARINGAN', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);
    $village = Wilayah::query()->create();
    $village->versi()->create([
        'version_no' => 1, 'code' => '3172011001', 'name' => 'PLUIT', 'level' => 'kelurahan',
        'parent_region_id' => $district->id, 'publication_status' => PublicationStatus::Published,
        'created_by' => $admin->id,
    ]);

    return $village;
}

function reconcileTestFacility(
    Pengguna $admin,
    Kategori $category,
    string $key,
    string $name,
    ?string $village,
    ?string $district,
    PublicationStatus $status = PublicationStatus::Draft,
    string $kind = 'school',
): VersiFasilitas {
    $facility = Fasilitas::query()->create(['external_key' => $key]);
    $version = $facility->versi()->create([
        'version_no' => 1,
        'name' => $name,
        'category_id' => $category->id,
        'region_id' => null,
        'publication_status' => $status,
        'created_by' => $admin->id,
    ]);

    if ($kind === 'school') {
        $version->sekolah()->create([
            'periode_data' => '2024',
            'kelurahan_sumber' => $village,
            'kecamatan_sumber' => $district,
        ]);
    } else {
        $version->lokasiPasar()->create([
            'periode_data' => '2024',
            'kelurahan_sumber' => $village,
            'kecamatan_sumber' => $district,
        ]);
    }

    return $version;
}

it('reports matches in a dry run without changing any facility version', function () {
    $admin = Pengguna::factory()->admin()->create();
    $village = reconcileTestVillage($admin);
    $category = Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $matched = reconcileTestFacility($admin, $category, 'school:1', 'SD Cocok', 'KEL. PLUIT', 'KEC. PENJARINGAN');
    $unmatched = reconcileTestFacility($admin, $category, 'school:2', 'SD Tidak Cocok', 'TIDAK ADA', 'PENJARINGAN');

    $this->artisan('app:reconcile-facility-regions')->assertSuccessful();

    expect($matched->fresh()->region_id)->toBeNull()
        ->and($unmatched->fresh()->region_id)->toBeNull()
        ->and(LogAudit::query()->where('action', 'facility.region_reconciled')->count())->toBe(0);
});

it('applies region IDs only to matching draft versions and records an audit entry', function () {
    $admin = Pengguna::factory()->admin()->create();
    $village = reconcileTestVillage($admin);
    $schoolCategory = Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $marketCategory = Kategori::query()->create(['name' => 'Pasar', 'slug' => 'pasar']);
    $matchedSchool = reconcileTestFacility($admin, $schoolCategory, 'school:1', 'SD Cocok', 'PLUIT', 'PENJARINGAN');
    $matchedMarket = reconcileTestFacility($admin, $marketCategory, 'market:1', 'Pasar Cocok', 'Pluit', 'Penjaringan', kind: 'market');
    $unmatched = reconcileTestFacility($admin, $schoolCategory, 'school:2', 'SD Tidak Cocok', 'TIDAK ADA', 'PENJARINGAN');
    $published = reconcileTestFacility($admin, $schoolCategory, 'school:3', 'SD Published', 'PLUIT', 'PENJARINGAN', PublicationStatus::Published);
    $versionCount = VersiFasilitas::query()->count();

    $this->artisan('app:reconcile-facility-regions', [
        '--apply' => true,
        '--actor' => $admin->email,
    ])->assertSuccessful();

    expect($matchedSchool->fresh()->region_id)->toBe($village->id)
        ->and($matchedMarket->fresh()->region_id)->toBe($village->id)
        ->and($unmatched->fresh()->region_id)->toBeNull()
        ->and($published->fresh()->region_id)->toBeNull()
        ->and(VersiFasilitas::query()->count())->toBe($versionCount)
        ->and(LogAudit::query()->where('action', 'facility.region_reconciled')->count())->toBe(2);
});

it('refuses to apply reconciliation for an account that is not an active admin', function () {
    $admin = Pengguna::factory()->admin()->create();
    $citizen = Pengguna::factory()->create();
    reconcileTestVillage($admin);
    $category = Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $matched = reconcileTestFacility($admin, $category, 'school:1', 'SD Cocok', 'PLUIT', 'PENJARINGAN');

    $this->artisan('app:reconcile-facility-regions', [
        '--apply' => true,
        '--actor' => $citizen->email,
    ])->assertFailed();

    expect($matched->fresh()->region_id)->toBeNull()
        ->and(LogAudit::query()->where('action', 'facility.region_reconciled')->count())->toBe(0);
});

it('limits reconciliation to the selected dataset', function () {
    $admin = Pengguna::factory()->admin()->create();
    $village = reconcileTestVillage($admin);
    $schoolCategory = Kategori::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah']);
    $marketCategory = Kategori::query()->create(['name' => 'Pasar', 'slug' => 'pasar']);
    $school = reconcileTestFacility($admin, $schoolCategory, 'school:1', 'SD Cocok', 'PLUIT', 'PENJARINGAN');
    $market = reconcileTestFacility($admin, $marketCategory, 'market:1', 'Pasar Cocok', 'PLUIT', 'PENJARINGAN', kind: 'market');

    $this->artisan('app:reconcile-facility-regions', [
        '--apply' => true,
        '--actor' => $admin->email,
        '--dataset' => 'market',
    ])->assertSuccessful();

    expect($market->fresh()->region_id)->toBe($village->id)
        ->and($school->fresh()->region_id)->toBeNull();
});
