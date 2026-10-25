<?php

namespace App\Http\Resources\Regions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRegionVersionResource extends JsonResource
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
            'region_id' => $this->region_id,
            'version_no' => $this->version_no,
            'code' => $this->code,
            'name' => $this->name,
            'level' => $this->level,
            'parent_region_id' => $this->parent_region_id,
            'source_id' => $this->source_id,
            'source_original_data' => $this->source_original_data,
            'publication_status' => $this->publication_status->value,
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
