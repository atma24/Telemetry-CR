<?php

use App\Jobs\Apbd\ProcessApbdImport;
use App\Models\Apbd\Apbd;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Services\Apbd\ApbdDataNormalizer;
use App\Services\Apbd\ApbdImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/** @return array<string, mixed> */
function apbdApiPayload(array $overrides = []): array
{
    return array_merge([
        'periode_data' => '202403',
        'tanggal' => '2024-03-31',
        'kode_opd' => '001.02',
        'nama_opd' => 'Dinas Pekerjaan Umum',
        'akun_jenis' => '5',
        'nama_akun' => 'Belanja Modal Jalan',
        'anggaran' => '1250000000.00',
        'realisasi' => '985000000.00',
    ], $overrides);
}

/**
 * @return array{file: UploadedFile, path: string}
 */
function makeApbdXlsxUpload(array $data): array
{
    $path = tempnam(sys_get_temp_dir(), 'apbd-import-');

    if ($path === false) {
        throw new RuntimeException('Unable to create the XLSX test file.');
    }

    $archive = new ZipArchive;

    if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to open the XLSX test file.');
    }

    $archive->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="APBD" sheetId="1" r:id="rId1"/></sheets></workbook>',
    );
    $archive->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
        .'Target="worksheets/sheet1.xml"/></Relationships>',
    );

    $columns = [
        'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
    ];
    $sharedStrings = [];
    $sharedStringIndexes = [];
    $addSharedString = function (string $value) use (&$sharedStrings, &$sharedStringIndexes): int {
        if (! array_key_exists($value, $sharedStringIndexes)) {
            $sharedStringIndexes[$value] = count($sharedStrings);
            $sharedStrings[] = $value;
        }

        return $sharedStringIndexes[$value];
    };
    $rows = [$columns, array_values($data)];
    $worksheet = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $rowNumber => $row) {
        $worksheet .= '<row r="'.($rowNumber + 1).'">';

        foreach ($row as $columnIndex => $value) {
            $column = chr(ord('A') + $columnIndex);
            $cell = $column.($rowNumber + 1);

            if ($rowNumber === 0) {
                $worksheet .= '<c r="'.$cell.'" t="s"><v>'.$addSharedString((string) $value).'</v></c>';

                continue;
            }

            if ($columnIndex === 0) {
                $worksheet .= '<c r="'.$cell.'"><v>202403</v></c>';
            } elseif ($columnIndex === 1) {
                $worksheet .= '<c r="'.$cell.'"><v>45382</v></c>';
            } elseif ($columnIndex === 2) {
                $worksheet .= '<c r="'.$cell.'" s="1"><v>102</v></c>';
            } elseif (in_array($columnIndex, [6, 7], true)) {
                $worksheet .= '<c r="'.$cell.'"><v>'.$value.'</v></c>';
            } else {
                $worksheet .= '<c r="'.$cell.'" t="s"><v>'.$addSharedString((string) $value).'</v></c>';
            }
        }

        $worksheet .= '</row>';
    }

    $worksheet .= '</sheetData></worksheet>';
    $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    foreach ($sharedStrings as $sharedString) {
        $escapedValue = htmlspecialchars($sharedString, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $sharedStringsXml .= '<si><t>'.$escapedValue.'</t></si>';
    }

    $sharedStringsXml .= '</sst>';
    $archive->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $archive->addFromString(
        'xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="1"><numFmt numFmtId="164" formatCode="00000"/></numFmts>'
        .'<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="164"/></cellXfs></styleSheet>',
    );
    $archive->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $archive->close();

    return [
        'file' => new UploadedFile(
            $path,
            'apbd.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        ),
        'path' => $path,
    ];
}

it('protects APBD administration endpoints from guests and citizens', function () {
    $this->getJson('/api/admin/apbd')->assertUnauthorized();

    $this->actingAs(Pengguna::factory()->create())
        ->getJson('/api/admin/apbd')
        ->assertForbidden();
});

it('issues expiring bearer tokens to active admins and allows revoking the current token', function () {
    $admin = Pengguna::factory()->admin()->create();
    $response = $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);
    $token = $response->json('data.access_token');
    $storedToken = PersonalAccessToken::query()->where('tokenable_id', $admin->id)->firstOrFail();

    expect($storedToken->expires_at)->not->toBeNull();

    $this->withToken($token)->getJson('/api/admin/apbd')->assertOk();
    $this->withToken($token)->deleteJson('/api/admin/token')->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $storedToken->id]);

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/admin/apbd')->assertUnauthorized();
});

it('does not issue admin bearer tokens for invalid or non-admin credentials', function () {
    $admin = Pengguna::factory()->admin()->create();
    $inactiveAdmin = Pengguna::factory()->admin()->create(['account_status' => 'inactive']);
    $citizen = Pengguna::factory()->create();

    $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'incorrect-password',
    ])->assertUnauthorized();

    $this->postJson('/api/admin/login', [
        'email' => $citizen->email,
        'password' => 'password',
    ])->assertUnauthorized();

    $this->postJson('/api/admin/login', [
        'email' => $inactiveAdmin->email,
        'password' => 'password',
    ])->assertUnauthorized();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('creates drafts and publishes only after validation without exposing internal fields publicly', function () {
    $admin = Pengguna::factory()->admin()->create();
    $payload = apbdApiPayload([
        'publication_status' => 'published',
        'created_by' => Str::uuid7()->toString(),
        'source_original_data' => ['private' => 'value'],
    ]);

    $created = $this->actingAs($admin)
        ->postJson('/api/admin/apbd', $payload)
        ->assertCreated()
        ->assertJsonPath('data.publication_status', 'draft')
        ->assertJsonPath('data.created_by', $admin->id)
        ->assertJsonPath('data.anggaran', '1250000000.00');

    $apbdId = $created->json('data.id');
    $this->assertDatabaseHas('apbd', [
        'id' => $apbdId,
        'publication_status' => 'draft',
        'created_by' => $admin->id,
        'source_original_data' => null,
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'apbd.created', 'entity_id' => $apbdId]);
    $this->getJson('/api/apbd')->assertOk()->assertJsonCount(0, 'data');

    $this->postJson("/api/admin/apbd/{$apbdId}/validate")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'validating');
    $this->postJson("/api/admin/apbd/{$apbdId}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'published');

    $publicRecord = $this->getJson("/api/apbd/{$apbdId}")
        ->assertOk()
        ->assertJsonPath('id', $apbdId)
        ->assertJsonPath('anggaran', '1250000000.00');

    foreach (['source_fingerprint', 'source_original_data', 'import_job_id', 'created_by', 'reviewed_by'] as $privateField) {
        $publicRecord->assertJsonMissingPath($privateField);
    }
});

it('keeps a published APBD record public until its validated correction is published', function () {
    $admin = Pengguna::factory()->admin()->create();
    $original = $this->actingAs($admin)
        ->postJson('/api/admin/apbd', apbdApiPayload())
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/admin/apbd/{$original}/validate")->assertOk();
    $this->postJson("/api/admin/apbd/{$original}/publish")->assertOk();

    $revision = $this->patchJson("/api/admin/apbd/{$original}", ['realisasi' => '750000000.25'])
        ->assertCreated()
        ->assertJsonPath('data.publication_status', 'draft')
        ->assertJsonPath('data.revises_id', $original)
        ->assertJsonPath('data.realisasi', '750000000.25');
    $revisionId = $revision->json('data.id');

    $this->getJson('/api/apbd')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $original)
        ->assertJsonPath('data.0.realisasi', '985000000.00');

    $this->postJson("/api/admin/apbd/{$revisionId}/validate")->assertOk();
    $this->postJson("/api/admin/apbd/{$revisionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'published');

    $this->assertDatabaseHas('apbd', ['id' => $original, 'publication_status' => 'superseded']);
    $this->getJson('/api/apbd')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $revisionId)
        ->assertJsonPath('data.0.realisasi', '750000000.25');
});

it('validates period and nonnegative amount values before creating APBD records', function () {
    $admin = Pengguna::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/apbd', apbdApiPayload([
            'periode_data' => '202413',
            'anggaran' => '-1.00',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['periode_data', 'anggaran']);

    $this->assertDatabaseCount('apbd', 0);
});

it('preserves the full supported decimal precision without converting amounts to floats', function () {
    $admin = Pengguna::factory()->admin()->create();
    $maximumAmount = str_repeat('9', 36).'.99';

    $created = $this->actingAs($admin)
        ->postJson('/api/admin/apbd', apbdApiPayload([
            'anggaran' => $maximumAmount,
            'realisasi' => $maximumAmount,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.anggaran', $maximumAmount)
        ->assertJsonPath('data.realisasi', $maximumAmount);

    $this->assertDatabaseHas('apbd', [
        'id' => $created->json('data.id'),
        'anggaran' => $maximumAmount,
        'realisasi' => $maximumAmount,
    ]);
});

it('filters published APBD records and dashboard counts by data period', function () {
    $marchRecord = Apbd::factory()->published()->create([
        'periode_data' => '202403',
        'nama_opd' => 'Dinas Transportasi',
    ]);
    Apbd::factory()->published()->create([
        'periode_data' => '202404',
        'nama_opd' => 'Dinas Lingkungan',
    ]);
    Apbd::factory()->create(['periode_data' => '202403']);

    $this->getJson('/api/apbd?periode_data=202403&search=Transportasi')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $marchRecord->id);

    $this->getJson('/api/dashboard?periode_data=202403')
        ->assertOk()
        ->assertExactJson([
            'facilities' => 0,
            'apbd_records' => 1,
            'datasets' => 0,
            'feedback_by_status' => [],
        ]);
});

it('publishes valid imported CSV rows automatically and keeps a private error report', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $duplicateData = [
        'periode_data' => '202403',
        'tanggal' => '2024-03-31',
        'kode_opd' => '001.02',
        'nama_opd' => 'Dinas Existing',
        'akun_jenis' => '5',
        'nama_akun' => 'Belanja Jalan',
        'anggaran' => '1000.00',
        'realisasi' => '400.00',
    ];
    Apbd::factory()->published()->create([
        ...$duplicateData,
        'source_fingerprint' => app(ApbdDataNormalizer::class)->fingerprint($duplicateData),
    ]);

    $csv = implode(',', array_keys($duplicateData))."\n"
        .implode(',', array_values($duplicateData))."\n"
        .'202404,2024-04-30,002.01,Dinas Baru,5,Belanja Gedung,2000.00,1500.25'."\n"
        .'202413,2024-04-30,003.01,Dinas Salah,5,Belanja Gedung,=2+2,100.00'."\n";
    $upload = UploadedFile::fake()->createWithContent('apbd.csv', $csv);

    $token = $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])
        ->assertCreated()
        ->json('data.access_token');

    $response = $this->withToken($token)
        ->withHeader('Accept', 'application/json')
        ->post('/api/admin/apbd/import', ['file' => $upload])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'uploaded')
        ->assertJsonPath('data.processed_rows', 0)
        ->assertJsonPath('data.progress_percentage', null)
        ->assertHeader('Location');

    $jobId = $response->json('data.id');
    $job = PekerjaanImpor::query()->findOrFail($jobId);
    Queue::assertPushed(ProcessApbdImport::class, fn (ProcessApbdImport $queuedJob): bool => $queuedJob->importJobId === $jobId);
    $this->assertDatabaseCount('apbd', 1);
    $this->withToken($token)->getJson("/api/admin/apbd/imports/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'uploaded');

    (new ProcessApbdImport($jobId))->handle(app(ApbdImportService::class));

    $job->refresh();
    $this->assertDatabaseCount('apbd', 2);
    $this->assertDatabaseHas('apbd', [
        'import_job_id' => $jobId,
        'publication_status' => 'published',
        'nama_opd' => 'Dinas Baru',
        'realisasi' => '1500.25',
        'created_by' => $admin->id,
        'reviewed_by' => $admin->id,
    ]);
    expect(Apbd::query()->where('import_job_id', $jobId)->value('published_at'))->not->toBeNull();
    $this->getJson('/api/apbd?periode_data=202404')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama_opd', 'Dinas Baru');
    expect($job->original_filename)->toBe('apbd.csv');
    expect($job->status)->toBe('completed_with_errors');
    expect($job->processed_rows)->toBe(3);
    expect($job->accepted_rows)->toBe(1);
    expect($job->rejected_rows)->toBe(2);
    Storage::disk('local')->assertExists($job->file_path);
    Storage::disk('local')->assertExists($job->error_report_path);
    expect(Storage::disk('local')->get($job->error_report_path))->toContain("'=2+2");

    $this->withToken($token)->getJson("/api/admin/apbd/imports/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed_with_errors')
        ->assertJsonPath('data.total_rows', 3)
        ->assertJsonPath('data.processed_rows', 3)
        ->assertJsonPath('data.accepted_rows', 1)
        ->assertJsonPath('data.rejected_rows', 2)
        ->assertJsonPath('data.progress_percentage', 100)
        ->assertJsonPath('data.error_report_url', route('api.admin.apbd.imports.errors', ['importJob' => $jobId]));

    $this->withToken($token)->getJson("/api/admin/apbd/imports/{$jobId}/errors")->assertOk();
    Auth::forgetGuards();
    $this->withoutToken()->getJson("/api/admin/apbd/imports/{$jobId}")->assertUnauthorized();
    $citizen = Pengguna::factory()->create();
    $this->actingAs($citizen)
        ->getJson("/api/admin/apbd/imports/{$jobId}")
        ->assertForbidden();
    $this->getJson("/api/admin/apbd/imports/{$jobId}/errors")
        ->assertForbidden();
});

it('rejects APBD import files larger than 50 MB', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $upload = UploadedFile::fake()->create('apbd.csv', 51201, 'text/csv');

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post('/api/admin/apbd/import', ['file' => $upload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    Queue::assertNothingPushed();
});

it('counts rows already persisted by the same import as accepted when a worker retries', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $data = [
        'periode_data' => '202403',
        'tanggal' => '2024-03-31',
        'kode_opd' => '001.02',
        'nama_opd' => 'Dinas Pekerjaan Umum',
        'akun_jenis' => '5',
        'nama_akun' => 'Belanja Modal Jalan',
        'anggaran' => '1250000000.00',
        'realisasi' => '985000000.00',
    ];
    $csv = implode(',', array_keys($data))."\n".implode(',', array_values($data))."\n";
    $upload = UploadedFile::fake()->createWithContent('retry.csv', $csv);

    $response = $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post('/api/admin/apbd/import', ['file' => $upload])
        ->assertAccepted();
    $jobId = $response->json('data.id');
    Apbd::factory()->create([
        ...$data,
        'import_job_id' => $jobId,
        'source_fingerprint' => app(ApbdDataNormalizer::class)->fingerprint($data),
    ]);

    (new ProcessApbdImport($jobId))->handle(app(ApbdImportService::class));

    $this->assertDatabaseCount('apbd', 1);
    $this->assertDatabaseHas('import_jobs', [
        'id' => $jobId,
        'status' => 'completed',
        'processed_rows' => 1,
        'accepted_rows' => 1,
        'rejected_rows' => 0,
    ]);
});

it('imports a valid XLSX worksheet without adding spreadsheet dependencies', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $data = apbdApiPayload(['kode_opd' => '00102']);
    $upload = makeApbdXlsxUpload($data);

    try {
        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post('/api/admin/apbd/import', ['file' => $upload['file']])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'uploaded');
        $jobId = $response->json('data.id');
        Queue::assertPushed(ProcessApbdImport::class, fn (ProcessApbdImport $queuedJob): bool => $queuedJob->importJobId === $jobId);

        (new ProcessApbdImport($jobId))->handle(app(ApbdImportService::class));

        $this->withHeader('Accept', 'application/json')
            ->getJson("/api/admin/apbd/imports/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.accepted_rows', 1)
            ->assertJsonPath('data.rejected_rows', 0);
    } finally {
        @unlink($upload['path']);
    }

    $this->assertDatabaseHas('apbd', [
        'periode_data' => '202403',
        'tanggal' => '2024-03-31',
        'kode_opd' => '00102',
        'nama_opd' => 'Dinas Pekerjaan Umum',
        'anggaran' => '1250000000.00',
        'publication_status' => 'published',
    ]);
});

it('marks an import failed when its queued source file is invalid', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = Pengguna::factory()->admin()->create();
    $upload = UploadedFile::fake()->createWithContent('invalid.csv', "wrong,headers\n1,2\n");

    $response = $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post('/api/admin/apbd/import', ['file' => $upload])
        ->assertAccepted();
    $jobId = $response->json('data.id');

    (new ProcessApbdImport($jobId))->handle(app(ApbdImportService::class));

    $this->getJson("/api/admin/apbd/imports/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.progress_percentage', null)
        ->assertJsonPath('data.failure_reason', 'Missing required columns: periode_data, tanggal, kode_opd, nama_opd, akun_jenis, nama_akun, anggaran, realisasi.');
    $this->assertDatabaseCount('apbd', 0);
});

it('archives published records on deletion and removes only an unpublished draft', function () {
    $admin = Pengguna::factory()->admin()->create();
    $published = Apbd::factory()->published()->create();
    $draft = Apbd::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/admin/apbd/{$published->id}")->assertNoContent();
    $this->assertDatabaseHas('apbd', ['id' => $published->id, 'publication_status' => 'archived']);
    $this->getJson("/api/apbd/{$published->id}")->assertNotFound();

    $this->deleteJson("/api/admin/apbd/{$draft->id}")->assertNoContent();
    $this->assertDatabaseMissing('apbd', ['id' => $draft->id]);
});
