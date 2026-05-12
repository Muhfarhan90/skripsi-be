<?php

namespace App\Http\Requests\Admin\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'is_required_for_certificate' => ['nullable', 'boolean'],
            'allow_resubmission' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ];
    }
}
