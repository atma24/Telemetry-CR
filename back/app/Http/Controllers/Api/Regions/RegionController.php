<?php

namespace App\Http\Controllers\Api\Regions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\IndexRegionRequest;
use App\Http\Resources\Regions\RegionResource;
use App\Services\Regions\RegionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    public function index(
        IndexRegionRequest $request,
        RegionService $regionService,
    ): AnonymousResourceCollection {
        return RegionResource::collection(
            $regionService->publishedRegions($request->validated()),
        );
    }

    public function show(string $region, RegionService $regionService): RegionResource
    {
        return new RegionResource($regionService->findPublished($region));
    }
}
