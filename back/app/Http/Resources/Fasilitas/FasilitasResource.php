<?php

namespace App\Http\Resources\Fasilitas;

use App\Models\Fasilitas\Fasilitas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fasilitas */
class FasilitasResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $versi = $this->versiTerpublikasi;

        return [
            'id' => $this->id,
            'version_id' => $versi?->id,
            'version_no' => $versi?->version_no,
            'name' => $versi?->name,
            'category_id' => $versi?->category_id,
            'region_id' => $versi?->region_id,
            'address' => $versi?->address,
            'latitude' => $versi?->latitude,
            'longitude' => $versi?->longitude,
            'attributes' => $versi?->attributes,
            'school' => $versi?->relationLoaded('sekolah') ? $versi->sekolah : null,
            'market_location' => $versi?->relationLoaded('lokasiPasar') ? $versi->lokasiPasar : null,
            'published_at' => $versi?->published_at?->toDateTimeString(),
        ];
    }
}
