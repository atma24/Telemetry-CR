<?php

namespace App\Http\Requests\Apbd;

use App\Enums\Wilayah\PublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexAdminApbdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'periode_data' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'kode_opd' => ['sometimes', 'string', 'max:100'],
            'akun_jenis' => ['sometimes', 'string', 'max:100'],
            'publication_status' => ['sometimes', Rule::enum(PublicationStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array{search?: string, periode_data?: string, kode_opd?: string, akun_jenis?: string, publication_status?: string, per_page?: int} */
    public function validatedFilters(): array
    {
        /** @var array{search?: string, periode_data?: string, kode_opd?: string, akun_jenis?: string, publication_status?: string, per_page?: int} $filters */
        $filters = $this->validated();

        if (isset($filters['per_page'])) {
            $filters['per_page'] = (int) $filters['per_page'];
        }

        return $filters;
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
