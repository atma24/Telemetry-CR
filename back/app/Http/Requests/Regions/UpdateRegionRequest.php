<?php

namespace App\Http\Requests\Regions;

use App\Enums\RegionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRegionRequest extends FormRequest
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
            'code' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'level' => ['sometimes', 'required', Rule::enum(RegionLevel::class)],
            'parent_region_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('regions', 'id')],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
            'source_original_data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fields = ['code', 'name', 'level', 'parent_region_id', 'source_id', 'source_original_data'];

            if (array_intersect($fields, array_keys($this->all())) === []) {
                $validator->errors()->add('region', 'At least one region field must be provided.');
            }
        }];
    }
}
