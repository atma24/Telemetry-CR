<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'periode_data' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = $this->input('periode_data');

            if (is_string($period) && preg_match('/^\d{6}$/', $period) && ((int) substr($period, 4, 2) < 1 || (int) substr($period, 4, 2) > 12)) {
                $validator->errors()->add('periode_data', 'The period month must be between 01 and 12.');
            }
        }];
    }
}
