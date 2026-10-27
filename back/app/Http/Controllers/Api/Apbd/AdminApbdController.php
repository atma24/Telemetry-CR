<?php

namespace App\Http\Controllers\Api\Apbd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Apbd\ImportApbdRequest;
use App\Http\Requests\Apbd\IndexAdminApbdRequest;
use App\Http\Requests\Apbd\StoreApbdRequest;
use App\Http\Requests\Apbd\UpdateApbdRequest;
use App\Http\Resources\Apbd\AdminApbdImportResource;
use App\Http\Resources\Apbd\AdminApbdResource;
use App\Models\PekerjaanImpor;
use App\Services\Apbd\AdminApbdService;
use App\Services\Apbd\ApbdImportService;
use App\Services\Apbd\ApbdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminApbdController extends Controller
{
    public function index(IndexAdminApbdRequest $request, ApbdService $apbdService): AnonymousResourceCollection
    {
        return AdminApbdResource::collection($apbdService->adminIndex($request->validatedFilters()));
    }

    public function store(StoreApbdRequest $request, AdminApbdService $service): JsonResponse
    {
        return (new AdminApbdResource($service->createDraft($request->validatedAttributes(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function import(ImportApbdRequest $request, ApbdImportService $service): JsonResponse
    {
        $importJob = $service->queueImport(
            $request->file('file'),
            $request->user(),
            $request->filled('source_id') ? $request->string('source_id')->toString() : null,
        );

        return (new AdminApbdImportResource($importJob))
            ->response()
            ->setStatusCode(202)
            ->header('Location', route('api.admin.apbd.imports.show', ['importJob' => $importJob->id]));
    }

    public function showImport(PekerjaanImpor $importJob): AdminApbdImportResource
    {
        abort_unless($importJob->target_type === 'apbd', 404);

        return new AdminApbdImportResource($importJob);
    }

    public function show(string $apbd, ApbdService $apbdService): AdminApbdResource
    {
        return new AdminApbdResource($apbdService->adminShow($apbd));
    }

    public function update(UpdateApbdRequest $request, string $apbd, AdminApbdService $service): AdminApbdResource
    {
        return new AdminApbdResource($service->update($apbd, $request->validatedAttributes(), $request->user()));
    }

    public function validateRecord(Request $request, string $apbd, AdminApbdService $service): AdminApbdResource
    {
        return new AdminApbdResource($service->validate($apbd, $request->user()));
    }

    public function publish(Request $request, string $apbd, AdminApbdService $service): AdminApbdResource
    {
        return new AdminApbdResource($service->publish($apbd, $request->user()));
    }

    public function destroy(Request $request, string $apbd, AdminApbdService $service): JsonResponse
    {
        $service->delete($apbd, $request->user());

        return response()->json(status: 204);
    }

    public function downloadErrors(PekerjaanImpor $importJob): StreamedResponse
    {
        abort_unless($importJob->target_type === 'apbd', 404);
        abort_unless(
            $importJob->error_report_path !== null && Storage::disk('local')->exists($importJob->error_report_path),
            404,
        );

        return Storage::disk('local')->download(
            $importJob->error_report_path,
            $importJob->id.'-errors.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
