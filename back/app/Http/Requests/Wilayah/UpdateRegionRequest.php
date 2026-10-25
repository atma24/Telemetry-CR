<?php

namespace App\Http\Requests\Wilayah;

use App\Enums\Wilayah\RegionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateRegionRequest extends FormRequest
{
    /**
     * @return array{
     *     code?: string,
     *     name?: string,
     *     level?: string,
     *     parent_region_id?: string|null,
     *     source_id?: string|null,
     *     source_original_data?: array<string, mixed>|null
     * }
     */
    public function validatedAttributes(): array
    {
        $validated = $this->validated();
        $attributes = [];

        foreach (['code', 'name', 'level'] as $field) {
            if (array_key_exists($field, $validated)) {
                if (! is_string($validated[$field])) {
                    throw ValidationException::withMessages(['region' => 'Validated region attributes have an invalid shape.']);
                }

                $attributes[$field] = $validated[$field];
            }
        }

        foreach (['parent_region_id', 'source_id'] as $field) {
            if (array_key_exists($field, $validated)) {
                if ($validated[$field] !== null && ! is_string($validated[$field])) {
                    throw ValidationException::withMessages(['region' => 'Validated region attributes have an invalid shape.']);
                }

                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('source_original_data', $validated)) {
            if ($validated['source_original_data'] !== null && ! is_array($validated['source_original_data'])) {
                throw ValidationException::withMessages(['region' => 'Validated region attributes have an invalid shape.']);
            }

            $attributes['source_original_data'] = $validated['source_original_data'];
        }

        return $attributes;
    }

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
