<?php

namespace App\Http\Controllers\Api\Wilayah\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wilayah\IndexAdminRegionRequest;
use App\Http\Requests\Wilayah\StoreRegionRequest;
use App\Http\Requests\Wilayah\UpdateRegionRequest;
use App\Http\Resources\Wilayah\AdminRegionResource;
use App\Http\Resources\Wilayah\AdminRegionVersionResource;
use App\Services\Wilayah\WilayahService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminWilayahController extends Controller
{
    public function index(IndexAdminRegionRequest $request, WilayahService $regionService): AnonymousResourceCollection
    {
        return AdminRegionVersionResource::collection($regionService->adminRegions($request->validated()));
    }

    public function store(StoreRegionRequest $request, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createDraft($request->validated(), $request->user()));
    }

    public function show(string $region, WilayahService $regionService): AdminRegionResource
    {
        return new AdminRegionResource($regionService->adminRegion($region));
    }

    public function update(UpdateRegionRequest $request, string $region, WilayahService $regionService): AdminRegionVersionResource
    {
        return new AdminRegionVersionResource($regionService->createVersion($region, $request->validated(), $request->user()));
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
