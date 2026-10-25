<?php

namespace App\Http\Resources\Regions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'versions' => AdminRegionVersionResource::collection($this->whenLoaded('versions')),
        ];
    }
}
