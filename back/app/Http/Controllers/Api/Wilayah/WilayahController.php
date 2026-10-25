<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wilayah\IndexRegionRequest;
use App\Http\Resources\Wilayah\RegionResource;
use App\Services\Wilayah\WilayahService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WilayahController extends Controller
{
    public function index(
        IndexRegionRequest $request,
        WilayahService $regionService,
    ): AnonymousResourceCollection {
        return RegionResource::collection(
            $regionService->publishedRegions($request->validated()),
        );
    }

    public function show(string $region, WilayahService $regionService): RegionResource
    {
        return new RegionResource($regionService->findPublished($region));
    }
}
