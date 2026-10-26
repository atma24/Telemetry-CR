<?php

namespace App\Http\Requests\Apbd;

class StoreApbdRequest extends ApbdDataRequest
{
    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return $this->dataRules();
    }
}
