<?php

namespace App\Http\Requests\Proyek;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProyekRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function validatedAttributes(): array
    {
        return $this->validated();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'uuid', Rule::exists('categories', 'id')],
            'agency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'planned_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'progress_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'project_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
            'regions' => ['sometimes', 'array'],
            'regions.*.region_id' => ['required', 'uuid', Rule::exists('regions', 'id'), 'distinct'],
            'regions.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $regions = $this->input('regions', []);
            if (is_array($regions) && collect($regions)->where('is_primary', true)->count() > 1) {
                $validator->errors()->add('regions', 'A project version may have at most one primary region.');
            }
        }];
    }
}
