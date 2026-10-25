<?php

use App\Enums\Wilayah\PublicationStatus;
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
use App\Models\Proyek\CakupanWilayahProyek;
use App\Models\Proyek\Proyek;
use App\Models\Proyek\VersiProyek;
use App\Models\SumberData;
use App\Models\Wilayah\VersiWilayah;
use App\Models\Wilayah\Wilayah;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        Proyek::class => 'projects',
        VersiProyek::class => 'project_versions',
        CakupanWilayahProyek::class => 'project_version_regions',
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

it('persists UUID project-region pivot records with primary and timestamp attributes', function () {
    $creator = Pengguna::factory()->create();
    $category = Kategori::query()->create(['name' => 'Transport', 'slug' => 'transport']);
    $project = Proyek::query()->create();
    $version = $project->versi()->create([
        'version_no' => 1,
        'name' => 'Bridge repairs',
        'category_id' => $category->id,
        'publication_status' => PublicationStatus::Draft,
        'created_by' => $creator->id,
    ]);
    $primaryRegion = Wilayah::query()->forceCreate([]);
    $secondaryRegion = Wilayah::query()->forceCreate([]);

    $version->wilayahCakupan()->attach($primaryRegion->id, ['is_primary' => true]);
    $version->wilayahCakupan()->attach($secondaryRegion->id, ['is_primary' => false]);

    $version->load('wilayahCakupan');
    $primaryPivot = $version->wilayahCakupan->firstWhere('id', $primaryRegion->id)?->pivot;

    expect($version->wilayahCakupan)->toHaveCount(2)
        ->and($primaryPivot)->toBeInstanceOf(CakupanWilayahProyek::class)
        ->and($primaryPivot->is_primary)->toBeTrue()
        ->and($primaryPivot->id)->toBeUuid()
        ->and($primaryPivot->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('connects the Indonesian relationships to the actual foreign keys and pivot model', function () {
    expect((new Wilayah)->versi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new Wilayah)->versiTerpublikasi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new Wilayah)->versi()->getForeignKeyName())->toBe('region_id')
        ->and((new Fasilitas)->versi()->getForeignKeyName())->toBe('facility_id')
        ->and((new Proyek)->versi()->getForeignKeyName())->toBe('project_id')
        ->and((new Dataset)->versi()->getForeignKeyName())->toBe('dataset_id')
        ->and((new VersiWilayah)->wilayahInduk()->getForeignKeyName())->toBe('parent_region_id')
        ->and((new VersiWilayah)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new VersiFasilitas)->pekerjaanImpor()->getForeignKeyName())->toBe('import_job_id')
        ->and((new VersiProyek)->wilayahCakupan())->toBeInstanceOf(BelongsToMany::class)
        ->and((new VersiProyek)->wilayahCakupan()->getPivotClass())->toBe(CakupanWilayahProyek::class)
        ->and((new VersiDataset)->versiDasar()->getForeignKeyName())->toBe('base_version_id')
        ->and((new VersiDataset)->sumberData()->getForeignKeyName())->toBe('source_id')
        ->and((new VersiDataset)->baris()->getForeignKeyName())->toBe('dataset_version_id')
        ->and((new UmpanBalik)->riwayat()->getForeignKeyName())->toBe('feedback_id')
        ->and((new UmpanBalik)->pengguna()->getForeignKeyName())->toBe('user_id')
        ->and((new Wilayah)->umpanBalik()->getForeignKeyName())->toBe('region_id')
        ->and((new VersiFasilitas)->kategori()->getForeignKeyName())->toBe('category_id')
        ->and((new VersiProyek)->pembuat()->getForeignKeyName())->toBe('created_by')
        ->and((new VersiDataset)->peninjau()->getForeignKeyName())->toBe('reviewed_by')
        ->and((new PekerjaanImpor)->sumberData()->getForeignKeyName())->toBe('source_id')
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
