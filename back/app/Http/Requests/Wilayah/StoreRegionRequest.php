<?php

namespace App\Http\Requests\Wilayah;

use App\Enums\Wilayah\RegionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreRegionRequest extends FormRequest
{
    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     level: string,
     *     parent_region_id?: string|null,
     *     source_id?: string|null,
     *     source_original_data?: array<string, mixed>|null
     * }
     *
     * @throws ValidationException
     */
    public function validatedAttributes(): array
    {
        $validated = $this->validated();

        if (! is_string($validated['code'] ?? null)
            || ! is_string($validated['name'] ?? null)
            || ! is_string($validated['level'] ?? null)) {
            throw ValidationException::withMessages(['region' => 'Validated region attributes have an invalid shape.']);
        }

        $parentRegionId = $validated['parent_region_id'] ?? null;
        $sourceId = $validated['source_id'] ?? null;
        $sourceOriginalData = $validated['source_original_data'] ?? null;

        if (($parentRegionId !== null && ! is_string($parentRegionId))
            || ($sourceId !== null && ! is_string($sourceId))
            || ($sourceOriginalData !== null && ! is_array($sourceOriginalData))) {
            throw ValidationException::withMessages(['region' => 'Validated region attributes have an invalid shape.']);
        }

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'level' => $validated['level'],
            'parent_region_id' => $parentRegionId,
            'source_id' => $sourceId,
            'source_original_data' => $sourceOriginalData,
        ];
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
