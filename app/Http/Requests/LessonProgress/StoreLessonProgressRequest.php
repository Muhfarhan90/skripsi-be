<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'exists:enrollments,id'],
            'lesson_id' => ['required', 'exists:lessons,id'],
            'progress_seconds' => ['nullable', 'integer', 'min:0'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
