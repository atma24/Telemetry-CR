<?php

namespace App\Http\Requests\Regions;

use App\Enums\PublicationStatus;
use App\Enums\RegionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminRegionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'level' => ['sometimes', Rule::enum(RegionLevel::class)],
            'parent_region_id' => ['sometimes', 'uuid'],
            'publication_status' => ['sometimes', Rule::enum(PublicationStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
