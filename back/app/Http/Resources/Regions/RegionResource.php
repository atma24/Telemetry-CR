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
            'parent' => $this->whenLoaded('parentRegion', function (): ?array {
                $parentVersion = $this->parentRegion?->publishedVersion;

                if ($parentVersion === null) {
                    return null;
                }

                return [
                    'id' => $this->parent_region_id,
                    'code' => $parentVersion->code,
                    'name' => $parentVersion->name,
                    'level' => $parentVersion->level,
                ];
            }),
            'published_at' => $this->published_at,
        ];
    }
}
