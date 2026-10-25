<?php

namespace App\Http\Controllers\Api\Proyek;

use App\Http\Controllers\Controller;
use App\Http\Resources\Proyek\ProyekResource;
use App\Services\Proyek\ProyekService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProyekController extends Controller
{
    public function index(Request $request, ProyekService $proyekService): AnonymousResourceCollection
    {
        return ProyekResource::collection($proyekService->index($request->integer('per_page', 15)));
    }

    public function show(string $project, ProyekService $proyekService): ProyekResource
    {
        return new ProyekResource($proyekService->show($project));
    }
}
