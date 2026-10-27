<?php

namespace App\Http\Controllers\Api\Wilayah\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wilayah\ImportRegionRequest;
use App\Http\Requests\Wilayah\IndexAdminRegionRequest;
use App\Http\Requests\Wilayah\StoreRegionRequest;
use App\Http\Requests\Wilayah\UpdateRegionRequest;
use App\Http\Resources\Wilayah\AdminRegionImportResource;
use App\Http\Resources\Wilayah\AdminRegionResource;
use App\Http\Resources\Wilayah\AdminRegionVersionResource;
use App\Models\PekerjaanImpor;
use App\Services\Wilayah\RegionImportService;
use App\Services\Wilayah\WilayahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminWilayahController extends Controller
{
    public function import(ImportRegionRequest $request, RegionImportService $service): JsonResponse
    {
        $job = $service->queueImport(
            $request->file('file'),
            $request->user(),
            $request->filled('source_id') ? $request->string('source_id')->toString() : null,
        );

        return (new AdminRegionImportResource($job))->response()->setStatusCode(202)
            ->header('Location', route('api.admin.regions.imports.show', ['importJob' => $job->id]));
    }

    public function showImport(PekerjaanImpor $importJob): AdminRegionImportResource
    {
        abort_unless($importJob->target_type === 'region_geojson', 404);

        return new AdminRegionImportResource($importJob);
    }

    public function downloadImportErrors(PekerjaanImpor $importJob): StreamedResponse
    {
        abort_unless($importJob->target_type === 'region_geojson', 404);
        abort_unless($importJob->error_report_path !== null && Storage::disk('local')->exists($importJob->error_report_path), 404);

        return Storage::disk('local')->download($importJob->error_report_path, $importJob->id.'-errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(IndexAdminRegionRequest $request, WilayahService $regionService): AnonymousResourceCollection
    {
        return AdminRegionVersionResource::collection($regionService->adminRegions($request->validated()));
    }

    public function store(StoreRegionRequest $request, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createDraft($request->validatedAttributes(), $request->user()));
    }

    public function show(string $region, WilayahService $regionService): AdminRegionResource
    {
        return new AdminRegionResource($regionService->adminRegion($region));
    }

    public function update(UpdateRegionRequest $request, string $region, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createVersion($region, $request->validatedAttributes(), $request->user()));
    }

    public function validateVersion(string $region, string $version, Request $request, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->validateVersion($region, $version, $request->user()));
    }

    public function publish(string $region, string $version, Request $request, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->publishVersion($region, $version, $request->user()));
    }

    public function archive(string $region, Request $request, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->archiveRegion($region, $request->user()));
    }
}
