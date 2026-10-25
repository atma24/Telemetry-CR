<?php

namespace App\Http\Controllers\Api\Fasilitas;

use App\Http\Controllers\Controller;
use App\Services\Fasilitas\FasilitasService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FasilitasController extends Controller
{
    /** @return LengthAwarePaginator<int, object> */
    public function index(Request $request, FasilitasService $fasilitasService): LengthAwarePaginator
    {
        return $fasilitasService->index($request->integer('per_page', 15));
    }

    public function show(string $facility, FasilitasService $fasilitasService): JsonResponse
    {
        return response()->json($fasilitasService->show($facility));
    }
}
