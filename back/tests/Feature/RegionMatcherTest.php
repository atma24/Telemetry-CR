<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Pengguna;
use App\Models\Wilayah\Wilayah;
use App\Services\Wilayah\RegionMatcher;

it('matches a village only when its normalized district parent is published and unique', function () {
    $admin = Pengguna::factory()->admin()->create();
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
    $otherDistrict = Wilayah::query()->create();
    $otherDistrict->versi()->create([
        'version_no' => 1, 'code' => '317202', 'name' => 'TANJUNG PRIOK', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);

    $matcher = app(RegionMatcher::class);

    expect($matcher->match('KEL. PLUIT', 'KEC. PENJARINGAN'))->toBe($village->id)
        ->and($matcher->match('pluit', 'penjaringan'))->toBe($village->id)
        ->and($matcher->match('PLUIT', 'TANJUNG PRIOK'))->toBeNull()
        ->and($matcher->match('PLUIT', null))->toBeNull();
});

it('normalizes approved spelling aliases, kepulauan abbreviations, and punctuation', function () {
    $admin = Pengguna::factory()->admin()->create();
    $district = Wilayah::query()->create();
    $district->versi()->create([
        'version_no' => 1, 'code' => '317107', 'name' => 'TANAH ABANG', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);
    $village = Wilayah::query()->create();
    $village->versi()->create([
        'version_no' => 1, 'code' => '3171071001', 'name' => 'TANAH SEREAL', 'level' => 'kelurahan',
        'parent_region_id' => $district->id, 'publication_status' => PublicationStatus::Published,
        'created_by' => $admin->id,
    ]);
    $seribuDistrict = Wilayah::query()->create();
    $seribuDistrict->versi()->create([
        'version_no' => 1, 'code' => '310101', 'name' => 'KEP. SERIBU UTARA', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);
    $seribuVillage = Wilayah::query()->create();
    $seribuVillage->versi()->create([
        'version_no' => 1, 'code' => '3101011001', 'name' => 'PULAU HARAPAN', 'level' => 'kelurahan',
        'parent_region_id' => $seribuDistrict->id, 'publication_status' => PublicationStatus::Published,
        'created_by' => $admin->id,
    ]);

    $matcher = app(RegionMatcher::class);

    expect($matcher->match('TANAHSAREAL', 'TANAH ABANG'))->toBe($village->id)
        ->and($matcher->match('PULAU HARAPAN', 'KEPULAUAN SERIBU UTARA'))->toBe($seribuVillage->id)
        ->and($matcher->key('Kep. Seribu  Utara!'))->toBe('KEPULAUANSERIBUUTARA');
});

it('returns no match when the published village or district name is ambiguous', function () {
    $admin = Pengguna::factory()->admin()->create();

    foreach (['317201', '317202'] as $code) {
        $district = Wilayah::query()->create();
        $district->versi()->create([
            'version_no' => 1, 'code' => $code, 'name' => 'PENJARINGAN', 'level' => 'kecamatan',
            'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
        ]);
    }

    $matcher = app(RegionMatcher::class);

    expect($matcher->match('PLUIT', 'PENJARINGAN'))->toBeNull();
});

it('ignores unpublished villages even when a published district exists', function () {
    $admin = Pengguna::factory()->admin()->create();
    $district = Wilayah::query()->create();
    $district->versi()->create([
        'version_no' => 1, 'code' => '317201', 'name' => 'PENJARINGAN', 'level' => 'kecamatan',
        'publication_status' => PublicationStatus::Published, 'created_by' => $admin->id,
    ]);
    $village = Wilayah::query()->create();
    $village->versi()->create([
        'version_no' => 1, 'code' => '3172011001', 'name' => 'PLUIT', 'level' => 'kelurahan',
        'parent_region_id' => $district->id, 'publication_status' => PublicationStatus::Draft,
        'created_by' => $admin->id,
    ]);

    expect(app(RegionMatcher::class)->match('PLUIT', 'PENJARINGAN'))->toBeNull();
});
