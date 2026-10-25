<?php

namespace App\Http\Controllers\Api\Proyek;

use App\Http\Controllers\Controller;
use App\Services\Proyek\ProyekService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekController extends Controller
{
    /** @return LengthAwarePaginator<int, object> */
    public function index(Request $request, ProyekService $proyekService): LengthAwarePaginator
    {
        return $proyekService->index($request->integer('per_page', 15));
    }

    public function show(string $project, ProyekService $proyekService): JsonResponse
    {
        return response()->json($proyekService->show($project));
    }
}
