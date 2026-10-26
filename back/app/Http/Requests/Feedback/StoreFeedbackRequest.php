<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'uuid', Rule::exists('facilities', 'id')],
            'apbd_id' => ['nullable', 'uuid', Rule::exists('apbd', 'id')->where('publication_status', 'published')],
            'region_id' => ['nullable', 'uuid', Rule::exists('regions', 'id')],
            'dataset_id' => ['nullable', 'uuid', Rule::exists('datasets', 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $targets = array_filter([
                $this->input('facility_id'),
                $this->input('apbd_id'),
                $this->input('region_id'),
                $this->input('dataset_id'),
            ], static fn (mixed $target): bool => $target !== null);

            if (count($targets) > 1) {
                $validator->errors()->add('facility_id', 'Feedback may reference no more than one target.');
            }
        }];
    }
}
