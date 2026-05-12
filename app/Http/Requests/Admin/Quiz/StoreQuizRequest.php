<?php

namespace App\Http\Requests\Admin\Quiz;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'title' => ['required', 'string', 'max:255', 'unique:quizzes,title'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'passing_score' => ['nullable', 'integer'],
            'weight' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'is_random' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer'],
            'open_at' => ['nullable', 'date'],
            'close_at' => ['nullable', 'date'],
        ];
    }
}
