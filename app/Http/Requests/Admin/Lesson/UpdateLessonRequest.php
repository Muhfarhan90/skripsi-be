<?php

    namespace App\Http\Requests\Admin\Lesson;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

    class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['sometimes', 'exists:sections,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:video,file'],
            'lesson_url' => ['nullable', 'string'],
            'lesson_file' => ['nullable', 'file', 'max:102400'], // 100MB max
            'duration' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
            'is_preview' => ['nullable', 'boolean'],
        ];
    }
}
