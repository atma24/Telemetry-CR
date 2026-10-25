<?php

namespace App\Http\Requests\Wilayah;

use App\Enums\Wilayah\RegionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::enum(RegionLevel::class)],
            'parent_region_id' => [
                'nullable',
                'required_unless:level,provinsi',
                'uuid',
                Rule::exists('regions', 'id'),
            ],
            'source_id' => ['nullable', 'uuid', Rule::exists('data_sources', 'id')],
            'source_original_data' => ['nullable', 'array'],
        ];
    }
}
