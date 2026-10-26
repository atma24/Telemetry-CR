<?php

namespace App\Http\Resources\Proyek;

use App\Models\Proyek\Proyek;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proyek */
class ProyekResource extends JsonResource
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
            'agency' => $versi?->agency,
            'start_date' => $versi?->start_date?->toDateString(),
            'planned_end_date' => $versi?->planned_end_date?->toDateString(),
            'progress_percentage' => $versi?->progress_percentage,
            'project_status' => $versi?->project_status,
            'attributes' => $versi?->attributes,
            'published_at' => $versi?->published_at?->toDateTimeString(),
            'regions' => $versi?->wilayahCakupan->map(fn ($wilayah): array => [
                'id' => $wilayah->id,
                'name' => $wilayah->versiTerpublikasi?->name,
                'is_primary' => (bool) $wilayah->pivot->is_primary,
            ])->values(),
        ];
    }
}
