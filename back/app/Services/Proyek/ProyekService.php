<?php

namespace App\Services\Proyek;

use App\Models\Proyek\Proyek;
use App\Models\Proyek\VersiProyek;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProyekService
{
    /** @return LengthAwarePaginator<int, Proyek> */
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Proyek::query()
            ->whereHas('versiTerpublikasi')
            ->with('versiTerpublikasi')
            ->orderBy(VersiProyek::query()
                ->select('name')
                ->whereColumn('project_id', 'projects.id')
                ->terpublikasi())
            ->orderBy('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $projectId): Proyek
    {
        return Proyek::query()
            ->whereKey($projectId)
            ->whereHas('versiTerpublikasi')
            ->with('versiTerpublikasi')
            ->firstOrFail();
    }
}
