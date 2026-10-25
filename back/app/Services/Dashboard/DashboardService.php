<?php

namespace App\Services\Dashboard;

use App\Models\Dataset\VersiDataset;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Feedback\UmpanBalik;
use App\Models\Proyek\VersiProyek;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array{facilities: int, projects: int, datasets: int, feedback_by_status: array<string, int>} */
    public function statistics(): array
    {
        $feedbackByStatus = UmpanBalik::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (int|string $count): int => (int) $count)
            ->all();

        return [
            'facilities' => VersiFasilitas::query()->terpublikasi()->count(),
            'projects' => VersiProyek::query()->terpublikasi()->count(),
            'datasets' => VersiDataset::query()->terpublikasi()->count(),
            'feedback_by_status' => $feedbackByStatus,
        ];
    }
}
