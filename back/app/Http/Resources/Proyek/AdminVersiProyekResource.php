<?php

namespace App\Http\Resources\Proyek;

use App\Models\Proyek\VersiProyek;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VersiProyek */
class AdminVersiProyekResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'version_no' => $this->version_no,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'agency' => $this->agency,
            'start_date' => $this->start_date?->toDateString(),
            'planned_end_date' => $this->planned_end_date?->toDateString(),
            'progress_percentage' => $this->progress_percentage,
            'project_status' => $this->project_status,
            'attributes' => $this->attributes,
            'source_id' => $this->source_id,
            'publication_status' => $this->publication_status->value,
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'published_at' => $this->published_at?->toDateTimeString(),
            'regions' => $this->whenLoaded('wilayahCakupan', fn () => $this->wilayahCakupan->map(fn ($wilayah): array => [
                'id' => $wilayah->id,
                'name' => $wilayah->versiTerpublikasi?->name,
                'is_primary' => (bool) $wilayah->pivot->is_primary,
            ])->values()),
        ];
    }
}
