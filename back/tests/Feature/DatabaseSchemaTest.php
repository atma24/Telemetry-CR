<?php

use App\Models\Pengguna;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

function createSchemaIdentity(string $entity, string $userId): string
{
    $id = Str::uuid7()->toString();
    $now = now();

    match ($entity) {
        'region' => DB::table('regions')->insert(['id' => $id, 'created_at' => $now, 'updated_at' => $now]),
        'facility' => DB::table('facilities')->insert(['id' => $id, 'created_at' => $now, 'updated_at' => $now]),
        'dataset' => DB::table('datasets')->insert([
            'id' => $id,
            'title' => 'Dataset',
            'slug' => Str::uuid7()->toString(),
            'lifecycle_status' => 'active',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
    };

    return $id;
}

function createSchemaCategory(): string
{
    $id = Str::uuid7()->toString();

    DB::table('categories')->insert([
        'id' => $id,
        'name' => 'Health',
        'slug' => Str::uuid7()->toString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function createSchemaVersion(string $entity, string $identityId, string $userId, int $versionNo, string $status): void
{
    $id = Str::uuid7()->toString();
    $now = now();

    match ($entity) {
        'region' => DB::table('region_versions')->insert([
            'id' => $id,
            'region_id' => $identityId,
            'version_no' => $versionNo,
            'code' => '31.71.'.$versionNo,
            'name' => 'Region version '.$versionNo,
            'level' => 'kecamatan',
            'publication_status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
        'facility' => DB::table('facility_versions')->insert([
            'id' => $id,
            'facility_id' => $identityId,
            'version_no' => $versionNo,
            'name' => 'Facility version '.$versionNo,
            'category_id' => createSchemaCategory(),
            'region_id' => createSchemaIdentity('region', $userId),
            'publication_status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
        'dataset' => DB::table('dataset_versions')->insert([
            'id' => $id,
            'dataset_id' => $identityId,
            'version_no' => $versionNo,
            'publication_status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
    };
}

/** @return array<string, mixed> */
function apbdSchemaAttributes(string $creatorId, array $overrides = []): array
{
    $now = now();

    return array_merge([
        'id' => Str::uuid7()->toString(),
        'periode_data' => '202403',
        'tanggal' => '2024-03-31',
        'kode_opd' => '001.02',
        'nama_opd' => 'Dinas Umum',
        'akun_jenis' => '5',
        'nama_akun' => 'Belanja Operasional',
        'anggaran' => '1000.00',
        'realisasi' => '800.00',
        'source_fingerprint' => hash('sha256', 'schema-test-fingerprint'),
        'publication_status' => 'draft',
        'created_by' => $creatorId,
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides);
}

test('a user is identified by a version 7 uuid', function () {
    $user = Pengguna::factory()->create();

    expect($user->id)->toBeUuid();
    expect(Uuid::fromString($user->id)->getVersion())->toBe(7);
});

test('a region keeps one published version while other versions stay unpublished', function () {
    $user = Pengguna::factory()->create();
    $regionId = createSchemaIdentity('region', $user->id);

    createSchemaVersion('region', $regionId, $user->id, 1, 'draft');
    createSchemaVersion('region', $regionId, $user->id, 2, 'published');
    createSchemaVersion('region', $regionId, $user->id, 3, 'draft');

    expect(DB::table('region_versions')->where('region_id', $regionId)->where('publication_status', 'published')->count())
        ->toBe(1);
});

test('a second published version of the same region, facility, or dataset is rejected', function (string $entity) {
    $user = Pengguna::factory()->create();
    $identityId = createSchemaIdentity($entity, $user->id);

    createSchemaVersion($entity, $identityId, $user->id, 1, 'published');

    expect(fn () => createSchemaVersion($entity, $identityId, $user->id, 2, 'published'))
        ->toThrow(QueryException::class);
})->with(['region', 'facility', 'dataset']);

test('feedback may reference one APBD record', function () {
    $user = Pengguna::factory()->create();
    $apbdId = Str::uuid7()->toString();
    DB::table('apbd')->insert(apbdSchemaAttributes($user->id, ['id' => $apbdId]));

    DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'apbd_id' => $apbdId,
        'subject' => 'Incorrect budget value',
        'message' => 'Please review this published APBD row.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('feedbacks')->where('apbd_id', $apbdId)->count())->toBe(1);
});

test('general feedback without a target record is accepted', function () {
    $user = Pengguna::factory()->create();

    DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'subject' => 'General suggestion',
        'message' => 'A suggestion about the platform.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('feedbacks')->where('user_id', $user->id)->count())->toBe(1);
});

test('feedback referencing two target records is rejected', function () {
    $user = Pengguna::factory()->create();

    expect(fn () => DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'region_id' => createSchemaIdentity('region', $user->id),
        'dataset_id' => createSchemaIdentity('dataset', $user->id),
        'subject' => 'Ambiguous',
        'message' => 'This feedback points at two records at once.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('rejected feedback requires a rejection reason', function () {
    $user = Pengguna::factory()->create();

    expect(fn () => DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'subject' => 'Rejected without a reason',
        'message' => 'This feedback was rejected without explaining why.',
        'status' => 'rejected',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('rejected feedback with a rejection reason is accepted', function () {
    $user = Pengguna::factory()->create();

    DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'subject' => 'Rejected with a reason',
        'message' => 'This feedback was rejected for a documented reason.',
        'status' => 'rejected',
        'rejection_reason' => 'The referenced record does not exist.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('feedbacks')->where('status', 'rejected')->value('rejection_reason'))
        ->toBe('The referenced record does not exist.');
});

test('facility coordinates are limited to the valid geographic range', function (string $column, float $value) {
    $user = Pengguna::factory()->create();
    $facilityId = createSchemaIdentity('facility', $user->id);

    expect(fn () => DB::table('facility_versions')->insert([
        'id' => Str::uuid7()->toString(),
        'facility_id' => $facilityId,
        'version_no' => 1,
        'name' => 'Out of range coordinates',
        'category_id' => createSchemaCategory(),
        'region_id' => createSchemaIdentity('region', $user->id),
        $column => $value,
        'publication_status' => 'draft',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([
    'latitude above the north pole' => ['latitude', 90.01],
    'latitude below the south pole' => ['latitude', -90.01],
    'longitude beyond the antimeridian' => ['longitude', 180.01],
    'longitude beyond the antimeridian to the west' => ['longitude', -180.01],
]);

test('duplicate APBD fingerprints are not enforced as a database uniqueness constraint', function () {
    $user = Pengguna::factory()->create();
    $fingerprint = hash('sha256', 'same candidate');

    DB::table('apbd')->insert(apbdSchemaAttributes($user->id, ['source_fingerprint' => $fingerprint]));
    DB::table('apbd')->insert(apbdSchemaAttributes($user->id, ['source_fingerprint' => $fingerprint]));

    expect(DB::table('apbd')->where('source_fingerprint', $fingerprint)->count())->toBe(2);
});

test('the APBD schema rejects negative amounts and invalid periods', function (string $field, string $value) {
    $user = Pengguna::factory()->create();

    expect(fn () => DB::table('apbd')->insert(apbdSchemaAttributes($user->id, [$field => $value])))
        ->toThrow(QueryException::class);
})->with([
    'negative budget' => ['anggaran', '-1.00'],
    'negative realization' => ['realisasi', '-0.01'],
    'invalid period month' => ['periode_data', '202413'],
]);

test('the current schema removes project tables without rewriting their migrations', function () {
    expect(Schema::hasTable('apbd'))->toBeTrue()
        ->and(Schema::hasTable('projects'))->toBeFalse()
        ->and(Schema::hasTable('project_versions'))->toBeFalse()
        ->and(Schema::hasTable('project_version_regions'))->toBeFalse();
});

test('personal access tokens store UUID morph IDs for user accounts', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue()
        ->and(Schema::getColumnType('personal_access_tokens', 'tokenable_id'))->toBe('uuid');
});

test('facilities retain shared versions with typed optional school and market details', function () {
    expect(Schema::hasTable('facilities'))->toBeTrue()
        ->and(Schema::hasTable('facility_versions'))->toBeTrue()
        ->and(Schema::hasTable('schools'))->toBeTrue()
        ->and(Schema::hasTable('market_locations'))->toBeTrue()
        ->and(Schema::hasColumn('region_versions', 'geometry'))->toBeTrue()
        ->and(Schema::hasColumn('region_versions', 'centroid_latitude'))->toBeTrue()
        ->and(Schema::hasColumn('import_jobs', 'warning_rows'))->toBeTrue()
        ->and(Schema::hasColumn('import_jobs', 'options'))->toBeTrue()
        ->and(Schema::getColumnType('facility_versions', 'region_id'))->toBe('uuid');
});

test('import jobs store row progress and private terminal failure reasons', function () {
    expect(Schema::hasColumn('import_jobs', 'processed_rows'))->toBeTrue()
        ->and(Schema::hasColumn('import_jobs', 'failure_reason'))->toBeTrue();
});
