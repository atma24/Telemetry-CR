<?php

namespace App\Services\Apbd;

use App\Models\Apbd\Apbd;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ApbdService
{
    /**
     * @param  array{search?: string, periode_data?: string, kode_opd?: string, akun_jenis?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Apbd>
     */
    public function publicIndex(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->terpublikasi()
            ->with('sumberData')
            ->orderByDesc('periode_data')
            ->orderByDesc('tanggal')
            ->orderBy('kode_opd')
            ->orderBy('akun_jenis')
            ->orderBy('id')
            ->paginate(max(1, min($filters['per_page'] ?? 15, 100)));
    }

    /**
     * @param  array{search?: string, periode_data?: string, kode_opd?: string, akun_jenis?: string, publication_status?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Apbd>
     */
    public function adminIndex(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->when(
                $filters['publication_status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('publication_status', $status),
            )
            ->with(['sumberData', 'pekerjaanImpor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($filters['per_page'] ?? 15, 100)));
    }

    public function publicShow(string $apbdId): Apbd
    {
        return Apbd::query()
            ->terpublikasi()
            ->with('sumberData')
            ->whereKey($apbdId)
            ->firstOrFail();
    }

    public function adminShow(string $apbdId): Apbd
    {
        return Apbd::query()
            ->with(['sumberData', 'pekerjaanImpor'])
            ->findOrFail($apbdId);
    }

    /**
     * @param  array{search?: string, periode_data?: string, kode_opd?: string, akun_jenis?: string}  $filters
     * @return Builder<Apbd>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Apbd::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama_opd', 'ilike', '%'.$search.'%')
                        ->orWhere('nama_akun', 'ilike', '%'.$search.'%');
                });
            })
            ->when($filters['periode_data'] ?? null, fn (Builder $query, string $period): Builder => $query->where('periode_data', $period))
            ->when($filters['kode_opd'] ?? null, fn (Builder $query, string $code): Builder => $query->where('kode_opd', $code))
            ->when($filters['akun_jenis'] ?? null, fn (Builder $query, string $type): Builder => $query->where('akun_jenis', $type));
    }
}
