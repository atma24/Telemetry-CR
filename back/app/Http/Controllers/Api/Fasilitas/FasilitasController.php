<?php

namespace App\Http\Controllers\Api\Fasilitas;

use App\Http\Controllers\Controller;
use App\Http\Resources\Fasilitas\FasilitasResource;
use App\Services\Fasilitas\FasilitasService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FasilitasController extends Controller
{
    public function index(Request $request, FasilitasService $fasilitasService): AnonymousResourceCollection
    {
        return FasilitasResource::collection($fasilitasService->index($request->integer('per_page', 15)));
    }

    public function show(string $facility, FasilitasService $fasilitasService): FasilitasResource
    {
        return new FasilitasResource($fasilitasService->show($facility));
    }
}
