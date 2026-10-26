<?php

namespace App\Http\Controllers\Api\Apbd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Apbd\IndexApbdRequest;
use App\Http\Resources\Apbd\ApbdResource;
use App\Services\Apbd\ApbdService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApbdController extends Controller
{
    public function index(IndexApbdRequest $request, ApbdService $apbdService): AnonymousResourceCollection
    {
        return ApbdResource::collection($apbdService->publicIndex($request->validatedFilters()));
    }

    public function show(string $apbd, ApbdService $apbdService): ApbdResource
    {
        return new ApbdResource($apbdService->publicShow($apbd));
    }
}
