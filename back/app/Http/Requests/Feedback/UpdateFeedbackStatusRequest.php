<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_review', 'in_progress', 'resolved', 'rejected'])],
            'response_message' => ['nullable', 'string'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string'],
        ];
    }
}
