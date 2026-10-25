<?php

namespace App\Services\Dataset;

use App\Models\Dataset\Dataset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatasetService
{
    /** @return LengthAwarePaginator<int, Dataset> */
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Dataset::query()
            ->whereHas('versiTerpublikasi')
            ->with(['kategori', 'versiTerpublikasi'])
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $datasetId): Dataset
    {
        return Dataset::query()
            ->whereKey($datasetId)
            ->whereHas('versiTerpublikasi')
            ->with(['kategori', 'versiTerpublikasi' => fn (HasOne $query) => $query->with(['sumberData', 'baris'])])
            ->firstOrFail();
    }
}
