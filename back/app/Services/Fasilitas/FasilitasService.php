<?php

namespace App\Services\Fasilitas;

use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FasilitasService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return VersiFasilitas::query()
            ->terpublikasi()
            ->with(['fasilitas', 'kategori', 'wilayah'])
            ->orderBy('name')
            ->orderBy('facility_id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $facilityId): object
    {
        return Fasilitas::query()
            ->whereKey($facilityId)
            ->firstOrFail()
            ->versiTerpublikasi()
            ->with(['fasilitas', 'kategori', 'wilayah'])
            ->firstOrFail();
    }
}
