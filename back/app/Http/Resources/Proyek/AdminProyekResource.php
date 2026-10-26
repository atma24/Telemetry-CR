<?php

namespace App\Http\Resources\Proyek;

use App\Models\Proyek\Proyek;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proyek */
class AdminProyekResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_key' => $this->external_key,
            'versions' => AdminVersiProyekResource::collection($this->whenLoaded('versi')),
        ];
    }
}
