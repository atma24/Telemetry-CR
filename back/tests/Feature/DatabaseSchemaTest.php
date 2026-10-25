<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/*
|--------------------------------------------------------------------------
| Schema fixture helpers
|--------------------------------------------------------------------------
|
| The domain tables have no Eloquent models yet, so the fixtures below write
| rows straight to the database. Each helper returns the identifier that the
| next call needs. The identity and the version are separate so a test can
| create two versions of the same record.
|
*/

function createIdentity(string $entity, string $userId): string
{
    $id = Str::uuid7()->toString();
    $now = now();

    match ($entity) {
        'region' => DB::table('regions')->insert(['id' => $id, 'created_at' => $now, 'updated_at' => $now]),
        'facility' => DB::table('facilities')->insert(['id' => $id, 'created_at' => $now, 'updated_at' => $now]),
        'project' => DB::table('projects')->insert(['id' => $id, 'created_at' => $now, 'updated_at' => $now]),
        'dataset' => DB::table('datasets')->insert([
            'id' => $id,
            'title' => 'Dataset',
            'slug' => 'dataset',
            'lifecycle_status' => 'active',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
    };

    return $id;
}

function createCategory(string $userId): string
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

function createVersion(string $entity, string $identityId, string $userId, int $versionNo, string $status): void
{
    $id = Str::uuid7()->toString();
    $now = now();
    $categoryId = createCategory($userId);

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
            'category_id' => $categoryId,
            'region_id' => createIdentity('region', $userId),
            'publication_status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
        'project' => DB::table('project_versions')->insert([
            'id' => $id,
            'project_id' => $identityId,
            'version_no' => $versionNo,
            'name' => 'Project version '.$versionNo,
            'category_id' => $categoryId,
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

test('a user is identified by a version 7 uuid', function () {
    $user = User::factory()->create();

    expect($user->id)->toBeUuid();
    expect(Uuid::fromString($user->id)->getVersion())->toBe(7);
});

test('a record keeps one published version while other versions stay unpublished', function () {
    $user = User::factory()->create();
    $regionId = createIdentity('region', $user->id);

    createVersion('region', $regionId, $user->id, 1, 'draft');
    createVersion('region', $regionId, $user->id, 2, 'published');
    createVersion('region', $regionId, $user->id, 3, 'draft');

    expect(DB::table('region_versions')->where('region_id', $regionId)->where('publication_status', 'published')->count())
        ->toBe(1);
});

test('a second published version of the same record is rejected', function (string $entity) {
    $user = User::factory()->create();
    $identityId = createIdentity($entity, $user->id);

    createVersion($entity, $identityId, $user->id, 1, 'published');

    expect(fn () => createVersion($entity, $identityId, $user->id, 2, 'published'))
        ->toThrow(QueryException::class);
})->with(['region', 'facility', 'project', 'dataset']);

test('feedback may reference one target record', function () {
    $user = User::factory()->create();
    $regionId = createIdentity('region', $user->id);

    DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'region_id' => $regionId,
        'subject' => 'Wrong name',
        'message' => 'This region is named incorrectly.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('feedbacks')->where('region_id', $regionId)->count())->toBe(1);
});

test('general feedback without a target record is accepted', function () {
    $user = User::factory()->create();

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
    $user = User::factory()->create();

    expect(fn () => DB::table('feedbacks')->insert([
        'id' => Str::uuid7()->toString(),
        'user_id' => $user->id,
        'region_id' => createIdentity('region', $user->id),
        'dataset_id' => createIdentity('dataset', $user->id),
        'subject' => 'Ambiguous',
        'message' => 'This feedback points at two records at once.',
        'status' => 'submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('rejected feedback requires a rejection reason', function () {
    $user = User::factory()->create();

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
    $user = User::factory()->create();

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

test('project progress is limited to the 0 to 100 range', function (float $progress) {
    $user = User::factory()->create();
    $projectId = createIdentity('project', $user->id);

    expect(fn () => DB::table('project_versions')->insert([
        'id' => Str::uuid7()->toString(),
        'project_id' => $projectId,
        'version_no' => 1,
        'name' => 'Out of range progress',
        'category_id' => createCategory($user->id),
        'progress_percentage' => $progress,
        'publication_status' => 'draft',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([100.01, -0.01]);

test('facility coordinates are limited to the valid geographic range', function (string $column, float $value) {
    $user = User::factory()->create();
    $facilityId = createIdentity('facility', $user->id);

    expect(fn () => DB::table('facility_versions')->insert([
        'id' => Str::uuid7()->toString(),
        'facility_id' => $facilityId,
        'version_no' => 1,
        'name' => 'Out of range coordinates',
        'category_id' => createCategory($user->id),
        'region_id' => createIdentity('region', $user->id),
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

test('a project version keeps at most one primary region', function () {
    $user = User::factory()->create();
    $projectId = createIdentity('project', $user->id);
    $versionId = Str::uuid7()->toString();
    $now = now();

    DB::table('project_versions')->insert([
        'id' => $versionId,
        'project_id' => $projectId,
        'version_no' => 1,
        'name' => 'Multi region project',
        'category_id' => createCategory($user->id),
        'publication_status' => 'draft',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('project_version_regions')->insert([
        'id' => Str::uuid7()->toString(),
        'project_version_id' => $versionId,
        'region_id' => createIdentity('region', $user->id),
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(fn () => DB::table('project_version_regions')->insert([
        'id' => Str::uuid7()->toString(),
        'project_version_id' => $versionId,
        'region_id' => createIdentity('region', $user->id),
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});

test('a project version may cover several regions as long as one is primary', function () {
    $user = User::factory()->create();
    $projectId = createIdentity('project', $user->id);
    $versionId = Str::uuid7()->toString();
    $now = now();

    DB::table('project_versions')->insert([
        'id' => $versionId,
        'project_id' => $projectId,
        'version_no' => 1,
        'name' => 'Multi region project',
        'category_id' => createCategory($user->id),
        'publication_status' => 'draft',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('project_version_regions')->insert([
        'id' => Str::uuid7()->toString(),
        'project_version_id' => $versionId,
        'region_id' => createIdentity('region', $user->id),
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('project_version_regions')->insert([
        'id' => Str::uuid7()->toString(),
        'project_version_id' => $versionId,
        'region_id' => createIdentity('region', $user->id),
        'is_primary' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(DB::table('project_version_regions')->where('project_version_id', $versionId)->count())->toBe(2);
});
