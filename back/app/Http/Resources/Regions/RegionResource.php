<?php

namespace App\Http\Resources\Regions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->region_id ?? $this->id,
            'version' => $this->version_no,
            'code' => $this->code,
            'name' => $this->name,
            'level' => $this->level,
            'parent_region_id' => $this->parent_region_id,
            'published_at' => $this->published_at,
        ];
    }
}
