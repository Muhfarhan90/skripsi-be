<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpsertLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_seconds' => ['nullable', 'integer', 'min:0'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
