<?php

namespace App\Http\Requests\Proyek;

class UpdateProyekRequest extends StoreProyekRequest
{
    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        $rules = parent::rules();
        foreach (['name', 'category_id'] as $field) {
            $rules[$field] = ['sometimes', 'required', ...array_slice($rules[$field], 1)];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function validatedAttributes(): array
    {
        return array_intersect_key($this->validated(), array_flip([
            'name', 'category_id', 'agency', 'start_date', 'planned_end_date', 'progress_percentage',
            'project_status', 'attributes', 'source_id', 'regions',
        ]));
    }
}
