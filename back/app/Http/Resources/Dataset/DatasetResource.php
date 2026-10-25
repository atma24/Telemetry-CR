<?php

namespace App\Http\Resources\Dataset;

use App\Models\Dataset\Dataset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dataset */
class DatasetResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $versi = $this->versiTerpublikasi;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'lifecycle_status' => $this->lifecycle_status,
            'version_id' => $versi?->id,
            'version_no' => $versi?->version_no,
            'schema_definition' => $versi?->schema_definition,
            'published_at' => $versi?->published_at?->toDateTimeString(),
        ];
    }
}
