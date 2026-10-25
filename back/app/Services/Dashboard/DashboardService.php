<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array{facilities: int, projects: int, datasets: int, feedback_by_status: array<string, int>} */
    public function statistics(): array
    {
        $feedbackByStatus = DB::table('feedbacks')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (int|string $count): int => (int) $count)
            ->all();

        return [
            'facilities' => DB::table('facility_versions')->where('publication_status', 'published')->count(),
            'projects' => DB::table('project_versions')->where('publication_status', 'published')->count(),
            'datasets' => DB::table('dataset_versions')->where('publication_status', 'published')->count(),
            'feedback_by_status' => $feedbackByStatus,
        ];
    }
}
