<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Apbd\Apbd;
use App\Models\Dataset\BarisDataset;
use App\Models\Dataset\Dataset;
use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Feedback\RiwayatUmpanBalik;
use App\Models\Feedback\UmpanBalik;
use App\Models\Kategori;
use App\Models\LogAudit;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\SumberData;
use App\Models\Wilayah\VersiWilayah;
use App\Models\Wilayah\Wilayah;
use Carbon\CarbonImmutable;

it('maps every Indonesian model explicitly to its existing database table', function () {
    $tablesByModel = [
        Pengguna::class => 'users',
        LogAudit::class => 'audit_logs',
        Kategori::class => 'categories',
        SumberData::class => 'data_sources',
        PekerjaanImpor::class => 'import_jobs',
        Wilayah::class => 'regions',
        VersiWilayah::class => 'region_versions',
        Fasilitas::class => 'facilities',
        VersiFasilitas::class => 'facility_versions',
        Apbd::class => 'apbd',
        Dataset::class => 'datasets',
        VersiDataset::class => 'dataset_versions',
        BarisDataset::class => 'dataset_rows',
        UmpanBalik::class => 'feedbacks',
        RiwayatUmpanBalik::class => 'feedback_events',
    ];

    foreach ($tablesByModel as $model => $table) {
        expect((new $model)->getTable())->toBe($table);
    }
});

it('persists APBD revision relationships', function () {
    $creator = Pengguna::factory()->create();
    $original = Apbd::factory()->published()->create(['created_by' => $creator->id]);
    $revision = Apbd::factory()->create([
        'revises_id' => $original->id,
        'created_by' => $creator->id,
    ]);

    expect($revision->revisiDari->id)->toBe($original->id)
        ->and($original->revisi->firstOrFail()->id)->toBe($revision->id);
});

it('connects the Indonesian relationships to the actual foreign keys', function () {
    expect((new Wilayah)->versi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new Wilayah)->versiTerpublikasi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new Wilayah)->versi()->getForeignKeyName())->toBe('region_id')
        ->and((new Fasilitas)->versi()->getForeignKeyName())->toBe('facility_id')
        ->and((new Dataset)->versi()->getForeignKeyName())->toBe('dataset_id')
        ->and((new Apbd)->umpanBalik()->getForeignKeyName())->toBe('apbd_id')
        ->and((new Apbd)->pembuat()->getForeignKeyName())->toBe('created_by')
        ->and((new Apbd)->peninjau()->getForeignKeyName())->toBe('reviewed_by')
        ->and((new Apbd)->revisiDari()->getForeignKeyName())->toBe('revises_id')
        ->and((new Apbd)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new Apbd)->pekerjaanImpor()->getForeignKeyName())->toBe('import_job_id')
        ->and((new VersiWilayah)->wilayahInduk()->getForeignKeyName())->toBe('parent_region_id')
        ->and((new VersiWilayah)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new VersiFasilitas)->pekerjaanImpor()->getForeignKeyName())->toBe('import_job_id')
        ->and((new VersiDataset)->versiDasar()->getForeignKeyName())->toBe('base_version_id')
        ->and((new VersiDataset)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new VersiDataset)->baris()->getForeignKeyName())->toBe('dataset_version_id')
        ->and((new UmpanBalik)->riwayat()->getForeignKeyName())->toBe('feedback_id')
        ->and((new UmpanBalik)->pengguna()->getForeignKeyName())->toBe('user_id')
        ->and((new Wilayah)->umpanBalik()->getForeignKeyName())->toBe('region_id')
        ->and((new VersiFasilitas)->kategori()->getForeignKeyName())->toBe('category_id')
        ->and((new VersiDataset)->peninjau()->getForeignKeyName())->toBe('reviewed_by')
        ->and((new PekerjaanImpor)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new PekerjaanImpor)->apbd()->getForeignKeyName())->toBe('import_job_id')
        ->and((new SumberData)->apbd()->getForeignKeyName())->toBe('source_id')
        ->and((new LogAudit)->pelaku()->getForeignKeyName())->toBe('actor_user_id')
        ->and((new RiwayatUmpanBalik)->pelaku()->getForeignKeyName())->toBe('actor_user_id');
});

it('keeps the latest version independent from the published version and applies model casts', function () {
    $creator = Pengguna::factory()->create();
    $dataset = Dataset::query()->create([
        'title' => 'Health indicators',
        'slug' => 'health-indicators',
        'lifecycle_status' => 'active',
        'created_by' => $creator->id,
    ]);
    $publishedVersion = $dataset->versi()->create([
        'version_no' => 1,
        'publication_status' => PublicationStatus::Published,
        'schema_definition' => ['columns' => ['year']],
        'created_by' => $creator->id,
        'published_at' => '2026-01-02 03:04:05',
    ]);
    $draftVersion = $dataset->versi()->create([
        'version_no' => 2,
        'publication_status' => PublicationStatus::Draft,
        'schema_definition' => ['columns' => ['year', 'total']],
        'original_file_path' => 'private/source.csv',
        'created_by' => $creator->id,
    ]);

    $loadedDataset = Dataset::query()
        ->with(['versiTerbaru', 'versiTerpublikasi'])
        ->findOrFail($dataset->id);

    expect($loadedDataset->versiTerbaru->id)->toBe($draftVersion->id)
        ->and($loadedDataset->versiTerpublikasi->id)->toBe($publishedVersion->id)
        ->and($draftVersion->publication_status)->toBe(PublicationStatus::Draft)
        ->and($draftVersion->schema_definition)->toBe(['columns' => ['year', 'total']])
        ->and($publishedVersion->published_at)->toBeInstanceOf(CarbonImmutable::class);
});
