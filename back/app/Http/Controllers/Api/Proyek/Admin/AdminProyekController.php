<?php

namespace App\Http\Controllers\Api\Proyek\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proyek\IndexAdminProyekRequest;
use App\Http\Requests\Proyek\StoreProyekRequest;
use App\Http\Requests\Proyek\UpdateProyekRequest;
use App\Http\Resources\Proyek\AdminProyekResource;
use App\Http\Resources\Proyek\AdminVersiProyekResource;
use App\Services\Proyek\AdminProyekService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProyekController extends Controller
{
    public function index(IndexAdminProyekRequest $request, AdminProyekService $service): AnonymousResourceCollection
    {
        return AdminVersiProyekResource::collection($service->adminProjects($request->validatedFilters()));
    }

    public function store(StoreProyekRequest $request, AdminProyekService $service): AdminVersiProyekResource
    {
        return new AdminVersiProyekResource($service->createDraft($request->validatedAttributes(), $request->user()));
    }

    public function show(string $project, AdminProyekService $service): AdminProyekResource
    {
        return new AdminProyekResource($service->adminProject($project));
    }

    public function update(UpdateProyekRequest $request, string $project, AdminProyekService $service): AdminVersiProyekResource
    {
        return new AdminVersiProyekResource($service->createVersion($project, $request->validatedAttributes(), $request->user()));
    }

    public function validateVersion(string $project, string $version, Request $request, AdminProyekService $service): AdminVersiProyekResource
    {
        return new AdminVersiProyekResource($service->validateVersion($project, $version, $request->user()));
    }

    public function publish(string $project, string $version, Request $request, AdminProyekService $service): AdminVersiProyekResource
    {
        return new AdminVersiProyekResource($service->publishVersion($project, $version, $request->user()));
    }

    public function archive(string $project, Request $request, AdminProyekService $service): AdminVersiProyekResource
    {
        return new AdminVersiProyekResource($service->archiveProject($project, $request->user()));
    }
}
