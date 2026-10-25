<?php

namespace App\Http\Controllers\Api\Dataset;

use App\Http\Controllers\Controller;
use App\Services\Dataset\DatasetService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    /** @return LengthAwarePaginator<int, object> */
    public function index(Request $request, DatasetService $datasetService): LengthAwarePaginator
    {
        return $datasetService->index($request->integer('per_page', 15));
    }

    public function show(string $dataset, DatasetService $datasetService): JsonResponse
    {
        return response()->json($datasetService->show($dataset));
    }
}
