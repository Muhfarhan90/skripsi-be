<?php

    namespace App\Http\Requests\Admin\Quiz;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

    class UpdateQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'exists:courses,id'],
            'section_id' => ['sometimes', 'exists:sections,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'passing_score' => ['nullable', 'integer'],
            'weight' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'is_random' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer'],
        ];
    }
}
