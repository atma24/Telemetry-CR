<?php

namespace App\Services\Fasilitas;

use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FasilitasService
{
    /** @return LengthAwarePaginator<int, Fasilitas> */
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Fasilitas::query()
            ->whereHas('versiTerpublikasi')
            ->with('versiTerpublikasi')
            ->orderBy(VersiFasilitas::query()
                ->select('name')
                ->whereColumn('facility_id', 'facilities.id')
                ->terpublikasi())
            ->orderBy('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $facilityId): Fasilitas
    {
        return Fasilitas::query()
            ->whereKey($facilityId)
            ->whereHas('versiTerpublikasi')
            ->with('versiTerpublikasi')
            ->firstOrFail();
    }
}
