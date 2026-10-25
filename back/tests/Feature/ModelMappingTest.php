<?php

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

it('connects the Indonesian relationships to the actual foreign keys and pivot model', function () {
    expect((new Wilayah)->versi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new Wilayah)->versiTerpublikasi()->getRelated())->toBeInstanceOf(VersiWilayah::class)
        ->and((new VersiWilayah)->wilayahInduk()->getForeignKeyName())->toBe('parent_region_id')
        ->and((new VersiProyek)->wilayahCakupan())->toBeInstanceOf(BelongsToMany::class)
        ->and((new VersiProyek)->wilayahCakupan()->getPivotClass())->toBe(CakupanWilayahProyek::class)
        ->and((new VersiDataset)->versiDasar()->getForeignKeyName())->toBe('base_version_id')
        ->and((new UmpanBalik)->riwayat()->getForeignKeyName())->toBe('feedback_id');
});
