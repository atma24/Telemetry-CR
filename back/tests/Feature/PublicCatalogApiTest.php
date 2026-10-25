<?php

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Dataset\Dataset;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Proyek\Proyek;
use App\Models\SumberData;
use App\Models\Wilayah\Wilayah;
use App\Services\Dataset\DatasetService;

it('serves the new public catalog and transparency endpoints', function () {
    $this->getJson('/api/facilities')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/datasets')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson([
            'facilities' => 0,
            'projects' => 0,
            'datasets' => 0,
            'feedback_by_status' => [],
        ]);
});

it('keeps public catalog response fields and identity and version ids while hiding unpublished and internal data', function () {
    $creator = Pengguna::factory()->create();
    $category = Kategori::query()->create([
        'name' => 'Health',
        'slug' => 'health',
    ]);
    $region = Wilayah::query()->forceCreate([]);
    $source = SumberData::query()->create([
        'name' => 'Open source',
        'source_type' => 'government',
        'source_url' => 'https://example.test/data',
        'license_info' => 'CC-BY',
    ]);

    $facility = Fasilitas::query()->create();
    $facilityVersion = $facility->versi()->create([
        'version_no' => 1,
        'name' => 'Clinic North',
        'category_id' => $category->id,
        'region_id' => $region->id,
        'address' => '1 Main Street',
        'latitude' => 12.1234567,
        'longitude' => 45.1234567,
        'attributes' => ['beds' => 12],
        'source_id' => $source->id,
        'import_job_id' => null,
        'source_original_data' => ['internal' => 'facility-value'],
        'publication_status' => PublicationStatus::Published,
        'created_by' => $creator->id,
        'published_at' => '2026-05-06 12:30:00',
    ]);
    $facilityDraft = $facility->versi()->create([
        'version_no' => 2,
        'name' => 'Unpublished clinic',
        'category_id' => $category->id,
        'region_id' => $region->id,
        'publication_status' => PublicationStatus::Draft,
        'created_by' => $creator->id,
    ]);

    $project = Proyek::query()->create();
    $projectVersion = $project->versi()->create([
        'version_no' => 1,
        'name' => 'Clinic renovation',
        'category_id' => $category->id,
        'agency' => 'Health Agency',
        'start_date' => '2026-01-01',
        'planned_end_date' => '2026-12-31',
        'progress_percentage' => 25.5,
        'project_status' => 'in_progress',
        'attributes' => ['phase' => 'construction'],
        'source_original_data' => ['internal' => 'project-value'],
        'publication_status' => PublicationStatus::Published,
        'created_by' => $creator->id,
        'published_at' => '2026-05-06 12:30:00',
    ]);
    $project->versi()->create([
        'version_no' => 2,
        'name' => 'Unpublished project',
        'category_id' => $category->id,
        'publication_status' => PublicationStatus::Draft,
        'created_by' => $creator->id,
    ]);

    $dataset = Dataset::query()->create([
        'title' => 'Clinic visits',
        'slug' => 'clinic-visits',
        'description' => 'Annual visits',
        'category_id' => $category->id,
        'lifecycle_status' => 'active',
        'created_by' => $creator->id,
    ]);
    $datasetVersion = $dataset->versi()->create([
        'version_no' => 1,
        'source_id' => $source->id,
        'import_job_id' => null,
        'publication_status' => PublicationStatus::Published,
        'schema_definition' => ['columns' => ['year', 'visits']],
        'original_file_path' => 'private/original.csv',
        'created_by' => $creator->id,
        'published_at' => '2026-05-06 12:30:00',
    ]);
    $datasetVersion->baris()->create([
        'row_number' => 1,
        'row_data' => ['year' => 2025, 'visits' => 120],
        'validation_status' => 'valid',
    ]);
    $draftDataset = Dataset::query()->create([
        'title' => 'Private dataset',
        'slug' => 'private-dataset',
        'lifecycle_status' => 'draft',
        'created_by' => $creator->id,
    ]);
    $draftDataset->versi()->create([
        'version_no' => 1,
        'publication_status' => PublicationStatus::Draft,
        'created_by' => $creator->id,
    ]);

    $facilityResponse = $this->getJson('/api/facilities')->assertOk()->assertJsonCount(1, 'data');
    $facilityResponse->assertJsonPath('data.0.id', $facility->id)
        ->assertJsonPath('data.0.version_id', $facilityVersion->id)
        ->assertJsonPath('data.0.name', 'Clinic North')
        ->assertJsonPath('data.0.attributes.beds', 12)
        ->assertJsonMissingPath('data.0.source_original_data')
        ->assertJsonMissingPath('data.0.import_job_id')
        ->assertJsonMissingPath('data.0.created_by')
        ->assertJsonMissingPath('data.0.reviewed_by');

    $projectResponse = $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
    $projectResponse->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.version_id', $projectVersion->id)
        ->assertJsonPath('data.0.name', 'Clinic renovation')
        ->assertJsonPath('data.0.start_date', '2026-01-01')
        ->assertJsonPath('data.0.published_at', '2026-05-06 12:30:00')
        ->assertJsonMissingPath('data.0.source_original_data')
        ->assertJsonMissingPath('data.0.import_job_id')
        ->assertJsonMissingPath('data.0.created_by')
        ->assertJsonMissingPath('data.0.reviewed_by');

    $datasetResponse = $this->getJson('/api/datasets')->assertOk()->assertJsonCount(1, 'data');
    $datasetResponse->assertJsonPath('data.0.id', $dataset->id)
        ->assertJsonPath('data.0.version_id', $datasetVersion->id)
        ->assertJsonPath('data.0.schema_definition.columns.0', 'year')
        ->assertJsonMissingPath('data.0.original_file_path')
        ->assertJsonMissingPath('data.0.import_job_id')
        ->assertJsonMissingPath('data.0.created_by')
        ->assertJsonMissingPath('data.0.reviewed_by');

    $this->getJson("/api/facilities/{$facility->id}")
        ->assertOk()
        ->assertJsonPath('id', $facility->id)
        ->assertJsonPath('version_id', $facilityVersion->id);
    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('id', $project->id)
        ->assertJsonPath('version_id', $projectVersion->id);
    $this->getJson("/api/datasets/{$dataset->id}")
        ->assertOk()
        ->assertJsonPath('id', $dataset->id)
        ->assertJsonPath('version_id', $datasetVersion->id)
        ->assertJsonMissingPath('original_file_path')
        ->assertJsonMissingPath('source_original_data');
    $this->getJson("/api/datasets/{$draftDataset->id}")->assertNotFound();

    $datasetDetail = app(DatasetService::class)->show($dataset->id);
    expect($datasetDetail->versiTerpublikasi->relationLoaded('sumberData'))->toBeTrue()
        ->and($datasetDetail->versiTerpublikasi->relationLoaded('baris'))->toBeTrue()
        ->and($datasetDetail->versiTerpublikasi->sumberData->id)->toBe($source->id)
        ->and($datasetDetail->versiTerpublikasi->baris)->toHaveCount(1);

    expect($facilityDraft->publication_status)->toBe(PublicationStatus::Draft);
    expect($facilityVersion->refresh()->latitude)->toBe('12.1234567')
        ->and($facilityVersion->publication_status)->toBe(PublicationStatus::Published);
});
