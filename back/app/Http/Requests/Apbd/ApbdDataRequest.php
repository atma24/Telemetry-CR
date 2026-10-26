<?php

namespace App\Http\Requests\Apbd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ApbdDataRequest extends FormRequest
{
    private const DATA_FIELDS = [
        'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    protected function dataRules(bool $partial = false): array
    {
        $presence = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'periode_data' => [...$presence, 'string', 'regex:/^\d{6}$/'],
            'tanggal' => [...$presence, 'date_format:Y-m-d'],
            'kode_opd' => [...$presence, 'string', 'max:100'],
            'nama_opd' => [...$presence, 'string', 'max:255'],
            'akun_jenis' => [...$presence, 'string', 'max:100'],
            'nama_akun' => [...$presence, 'string', 'max:255'],
            'anggaran' => [...$presence, 'string', 'max:39', 'regex:/^\d{1,36}(?:\.\d{1,2})?$/'],
            'realisasi' => [...$presence, 'string', 'max:39', 'regex:/^\d{1,36}(?:\.\d{1,2})?$/'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedAttributes(): array
    {
        return $this->safe()->only(self::DATA_FIELDS);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('periode_data') || ! $this->exists('periode_data')) {
                return;
            }

            $period = $this->string('periode_data')->toString();
            $month = (int) substr($period, 4, 2);

            if ($month < 1 || $month > 12) {
                $validator->errors()->add('periode_data', 'The period month must be between 01 and 12.');
            }
        }];
    }
}
