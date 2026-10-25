<?php

namespace App\Http\Controllers\Api\Dataset;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dataset\DatasetResource;
use App\Services\Dataset\DatasetService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DatasetController extends Controller
{
    public function index(Request $request, DatasetService $datasetService): AnonymousResourceCollection
    {
        return DatasetResource::collection($datasetService->index($request->integer('per_page', 15)));
    }

    public function show(string $dataset, DatasetService $datasetService): DatasetResource
    {
        return new DatasetResource($datasetService->show($dataset));
    }
}
