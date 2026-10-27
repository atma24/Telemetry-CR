<?php

namespace App\Http\Requests\Fasilitas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFasilitasRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function validatedAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $allowedFields = [
            'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude',
            'attributes', 'source_id', 'source_original_data',
        ];

        return array_intersect_key($validated, array_flip($allowedFields));
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'uuid', Rule::exists('categories', 'id')],
            'region_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('regions', 'id')],
            'address' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,7', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,7', 'between:-180,180'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'attributes.accessibility' => ['sometimes', 'nullable', 'array'],
            'attributes.accessibility.*' => ['nullable', 'boolean'],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
            'source_original_data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowedFields = [
                'name', 'category_id', 'region_id', 'address', 'latitude', 'longitude',
                'attributes', 'source_id', 'source_original_data',
            ];

            if (array_intersect($allowedFields, array_keys($this->all())) === []) {
                $validator->errors()->add('facility', 'At least one facility field must be provided.');
            }

            if ($validator->errors()->hasAny(['latitude', 'longitude'])) {
                return;
            }

            $latitudeProvided = $this->exists('latitude');
            $longitudeProvided = $this->exists('longitude');

            if ($latitudeProvided !== $longitudeProvided) {
                $missingField = $latitudeProvided ? 'longitude' : 'latitude';
                $validator->errors()->add($missingField, 'Latitude and longitude must be supplied together.');

                return;
            }

            if ($latitudeProvided && (($this->input('latitude') === null) !== ($this->input('longitude') === null))) {
                $validator->errors()->add('latitude', 'Latitude and longitude must both be numeric or both be null.');
                $validator->errors()->add('longitude', 'Latitude and longitude must both be numeric or both be null.');
            }
        }];
    }
}
