<?php

namespace App\Services\Dataset;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DatasetService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return $this->publishedDatasets()->orderBy('datasets.title')->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $datasetId): object
    {
        return $this->publishedDatasets()->where('datasets.id', $datasetId)->firstOrFail();
    }

    private function publishedDatasets(): Builder
    {
        return DB::table('dataset_versions')
            ->join('datasets', 'datasets.id', '=', 'dataset_versions.dataset_id')
            ->where('dataset_versions.publication_status', 'published')
            ->select([
                'datasets.id', 'datasets.title', 'datasets.slug', 'datasets.description',
                'datasets.category_id', 'datasets.lifecycle_status', 'dataset_versions.id as version_id',
                'dataset_versions.version_no', 'dataset_versions.schema_definition',
                'dataset_versions.published_at',
            ]);
    }
}
