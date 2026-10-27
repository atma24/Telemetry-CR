<?php

namespace App\Http\Resources\Fasilitas;

use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Kategori;
use App\Models\SumberData;
use App\Models\Wilayah\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VersiFasilitas */
class AdminVersiFasilitasResource extends JsonResource
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
            'facility_id' => $this->facility_id,
            'version_no' => $this->version_no,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('kategori', fn (Kategori $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]),
            'region_id' => $this->region_id,
            'region' => $this->whenLoaded('wilayah', fn (Wilayah $region): array => [
                'id' => $region->id,
                'name' => $region->versiTerpublikasi?->name,
            ]),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'attributes' => $this->attributes,
            'school' => $this->whenLoaded('sekolah'),
            'market_location' => $this->whenLoaded('lokasiPasar'),
            'source_id' => $this->source_id,
            'source' => $this->whenLoaded('sumberData', fn (SumberData $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'source_type' => $source->source_type,
                'source_url' => $source->source_url,
                'license_info' => $source->license_info,
            ]),
            'import_job_id' => $this->import_job_id,
            'source_original_data' => $this->source_original_data,
            'publication_status' => $this->publication_status->value,
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
