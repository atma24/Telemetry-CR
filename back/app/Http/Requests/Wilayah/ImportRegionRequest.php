<?php

namespace App\Http\Requests\Wilayah;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/json,application/geo+json,application/octet-stream,text/plain', 'extensions:geojson,json'],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
        ];
    }
}
