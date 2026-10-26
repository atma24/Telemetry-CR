<?php

namespace App\Http\Requests\Proyek;

use App\Enums\Wilayah\PublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminProyekRequest extends FormRequest
{
    /** @return array{search?: string, category_id?: string, publication_status?: string, per_page?: int} */
    public function validatedFilters(): array
    {
        /** @var array{search?: string, category_id?: string, publication_status?: string, per_page?: int} $filters */
        $filters = $this->validated();
        if (isset($filters['per_page'])) {
            $filters['per_page'] = (int) $filters['per_page'];
        }

        return $filters;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'uuid', Rule::exists('categories', 'id')],
            'publication_status' => ['sometimes', Rule::enum(PublicationStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
