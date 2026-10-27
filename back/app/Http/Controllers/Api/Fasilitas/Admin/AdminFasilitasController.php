<?php

namespace App\Http\Controllers\Api\Fasilitas\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasilitas\ImportFasilitasRequest;
use App\Http\Requests\Fasilitas\IndexAdminFasilitasRequest;
use App\Http\Requests\Fasilitas\StoreFasilitasRequest;
use App\Http\Requests\Fasilitas\UpdateFasilitasRequest;
use App\Http\Resources\Fasilitas\AdminFacilityImportResource;
use App\Http\Resources\Fasilitas\AdminFasilitasResource;
use App\Http\Resources\Fasilitas\AdminVersiFasilitasResource;
use App\Models\PekerjaanImpor;
use App\Services\Fasilitas\AdminFasilitasService;
use App\Services\Fasilitas\FacilityImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFasilitasController extends Controller
{
    public function import(ImportFasilitasRequest $request, FacilityImportService $service): JsonResponse
    {
        $job = $service->queueImport(
            $request->file('file'),
            $request->string('dataset_type')->toString(),
            $request->user(),
            $request->filled('source_id') ? $request->string('source_id')->toString() : null,
            $request->filled('periode_data') ? $request->string('periode_data')->toString() : null,
        );

        return (new AdminFacilityImportResource($job))->response()->setStatusCode(202)
            ->header('Location', route('api.admin.facilities.imports.show', ['importJob' => $job->id]));
    }

    public function showImport(PekerjaanImpor $importJob): AdminFacilityImportResource
    {
        abort_unless(in_array($importJob->target_type, ['facility_school', 'facility_market', 'facility_osm'], true), 404);

        return new AdminFacilityImportResource($importJob);
    }

    public function downloadImportErrors(PekerjaanImpor $importJob): StreamedResponse
    {
        abort_unless(in_array($importJob->target_type, ['facility_school', 'facility_market', 'facility_osm'], true), 404);
        abort_unless($importJob->error_report_path !== null && Storage::disk('local')->exists($importJob->error_report_path), 404);

        return Storage::disk('local')->download($importJob->error_report_path, $importJob->id.'-errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(IndexAdminFasilitasRequest $request, AdminFasilitasService $fasilitasService): AnonymousResourceCollection
    {
        return AdminVersiFasilitasResource::collection($fasilitasService->adminFacilities($request->validatedFilters()));
    }

    public function store(StoreFasilitasRequest $request, AdminFasilitasService $fasilitasService): AdminVersiFasilitasResource
    {
        return new AdminVersiFasilitasResource(
            $fasilitasService->createDraft($request->validatedAttributes(), $request->user()),
        );
    }

    public function show(string $facility, AdminFasilitasService $fasilitasService): AdminFasilitasResource
    {
        return new AdminFasilitasResource($fasilitasService->adminFacility($facility));
    }

    public function update(
        UpdateFasilitasRequest $request,
        string $facility,
        AdminFasilitasService $fasilitasService,
    ): AdminVersiFasilitasResource {
        return new AdminVersiFasilitasResource(
            $fasilitasService->createVersion($facility, $request->validatedAttributes(), $request->user()),
        );
    }

    public function validateVersion(
        string $facility,
        string $version,
        Request $request,
        AdminFasilitasService $fasilitasService,
    ): AdminVersiFasilitasResource {
        return new AdminVersiFasilitasResource(
            $fasilitasService->validateVersion($facility, $version, $request->user()),
        );
    }

    public function publish(
        string $facility,
        string $version,
        Request $request,
        AdminFasilitasService $fasilitasService,
    ): AdminVersiFasilitasResource {
        return new AdminVersiFasilitasResource(
            $fasilitasService->publishVersion($facility, $version, $request->user()),
        );
    }

    public function archive(
        string $facility,
        Request $request,
        AdminFasilitasService $fasilitasService,
    ): AdminVersiFasilitasResource {
        return new AdminVersiFasilitasResource($fasilitasService->archiveFacility($facility, $request->user()));
    }
}
