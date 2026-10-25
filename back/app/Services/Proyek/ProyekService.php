<?php

namespace App\Services\Proyek;

use App\Models\Proyek\Proyek;
use App\Models\Proyek\VersiProyek;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProyekService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return VersiProyek::query()
            ->terpublikasi()
            ->with(['proyek', 'kategori', 'wilayahCakupan'])
            ->orderBy('name')
            ->orderBy('project_id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $projectId): object
    {
        return Proyek::query()
            ->whereKey($projectId)
            ->firstOrFail()
            ->versiTerpublikasi()
            ->with(['proyek', 'kategori', 'wilayahCakupan'])
            ->firstOrFail();
    }
}
