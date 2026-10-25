<?php

namespace App\Services\Proyek;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProyekService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return $this->publishedProjects()->orderBy('project_versions.name')->paginate(max(1, min($perPage, 100)));
    }

    public function show(string $projectId): object
    {
        return $this->publishedProjects()->where('projects.id', $projectId)->firstOrFail();
    }

    private function publishedProjects(): Builder
    {
        return DB::table('project_versions')
            ->join('projects', 'projects.id', '=', 'project_versions.project_id')
            ->where('project_versions.publication_status', 'published')
            ->select([
                'projects.id', 'project_versions.id as version_id', 'project_versions.version_no',
                'project_versions.name', 'project_versions.category_id', 'project_versions.agency',
                'project_versions.start_date', 'project_versions.planned_end_date',
                'project_versions.progress_percentage', 'project_versions.project_status',
                'project_versions.attributes', 'project_versions.published_at',
            ]);
    }
}
