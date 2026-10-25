<?php

namespace App\Http\Resources\Wilayah;

use App\Models\Wilayah\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wilayah */
class AdminRegionResource extends JsonResource
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
            'created_at' => $this->created_at,
            'versions' => AdminRegionVersionResource::collection($this->whenLoaded('versi')),
        ];
    }
}
