<?php

namespace App\Http\Controllers\Api\Fasilitas\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasilitas\IndexAdminFasilitasRequest;
use App\Http\Requests\Fasilitas\StoreFasilitasRequest;
use App\Http\Requests\Fasilitas\UpdateFasilitasRequest;
use App\Http\Resources\Fasilitas\AdminFasilitasResource;
use App\Http\Resources\Fasilitas\AdminVersiFasilitasResource;
use App\Services\Fasilitas\AdminFasilitasService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminFasilitasController extends Controller
{
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
