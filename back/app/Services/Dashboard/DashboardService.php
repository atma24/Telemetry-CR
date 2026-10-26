<?php

namespace App\Services\Dashboard;

use App\Models\Apbd\Apbd;
use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Feedback\UmpanBalik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array{facilities: int, apbd_records: int, datasets: int, feedback_by_status: array<string, int>} */
    public function statistics(?string $period = null): array
    {
        $feedbackByStatus = UmpanBalik::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (int|string $count): int => (int) $count)
            ->all();

        return [
            'facilities' => VersiFasilitas::query()->terpublikasi()->count(),
            'apbd_records' => Apbd::query()
                ->terpublikasi()
                ->when($period, fn (Builder $query, string $period): Builder => $query->where('periode_data', $period))
                ->count(),
            'datasets' => VersiDataset::query()->terpublikasi()->count(),
            'feedback_by_status' => $feedbackByStatus,
        ];
    }
}
