<?php

namespace App\Http\Requests\Fasilitas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportFasilitasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dataset_type' => ['required', Rule::in(['school', 'market', 'osm'])],
            'file' => [
                'required', 'file', 'max:20480',
                'mimetypes:text/plain,text/csv,application/csv,application/json,application/geo+json,application/octet-stream,text/html,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'extensions:csv,xlsx,xls,geojson,json',
            ],
            'periode_data' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
        ];
    }
}
