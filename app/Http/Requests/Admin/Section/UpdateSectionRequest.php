<?php

    namespace App\Http\Requests\Admin\Section;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

    class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'exists:courses,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_locked' => ['nullable', 'boolean'],
        ];
    }
}
