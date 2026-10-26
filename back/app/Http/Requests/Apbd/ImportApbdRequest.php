<?php

namespace App\Http\Requests\Apbd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportApbdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'extensions:csv,xlsx',
                'max:20480',
            ],
            'source_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('data_sources', 'id')],
        ];
    }
}
