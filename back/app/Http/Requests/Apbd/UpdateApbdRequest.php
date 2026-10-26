<?php

namespace App\Http\Requests\Apbd;

use Illuminate\Validation\Validator;

class UpdateApbdRequest extends ApbdDataRequest
{
    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return $this->dataRules(partial: true);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            parent::after()[0]($validator);

            if (array_intersect(array_keys($this->all()), [
                'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
            ]) === []) {
                $validator->errors()->add('apbd', 'At least one APBD data field must be provided.');
            }
        }];
    }
}
