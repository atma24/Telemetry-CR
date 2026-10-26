<?php

namespace App\Http\Resources\Fasilitas;

use App\Models\Fasilitas\Fasilitas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fasilitas */
class AdminFasilitasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'versions' => AdminVersiFasilitasResource::collection($this->whenLoaded('versi')),
        ];
    }
}
