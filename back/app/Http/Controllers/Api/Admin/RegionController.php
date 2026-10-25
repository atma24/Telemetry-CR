<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\IndexAdminRegionRequest;
use App\Http\Requests\Regions\StoreRegionRequest;
use App\Http\Requests\Regions\UpdateRegionRequest;
use App\Http\Resources\Regions\AdminRegionResource;
use App\Http\Resources\Regions\AdminRegionVersionResource;
use App\Services\Regions\RegionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    public function index(IndexAdminRegionRequest $request, RegionService $regionService): AnonymousResourceCollection
    {
        return AdminRegionVersionResource::collection($regionService->adminRegions($request->validated()));
    }

    public function store(StoreRegionRequest $request, RegionService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createDraft($request->validated(), $request->user()));
    }

    public function show(string $region, RegionService $regionService): AdminRegionResource
    {
        return new AdminRegionResource($regionService->adminRegion($region));
    }

    public function update(UpdateRegionRequest $request, string $region, RegionService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createVersion($region, $request->validated(), $request->user()));
    }

    public function validateVersion(string $region, string $version, Request $request, RegionService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->validateVersion($region, $version, $request->user()));
    }

    public function publish(string $region, string $version, Request $request, RegionService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->publishVersion($region, $version, $request->user()));
    }

    public function archive(string $region, Request $request, RegionService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->archiveRegion($region, $request->user()));
    }
}
