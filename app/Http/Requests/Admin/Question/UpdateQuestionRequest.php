<?php

namespace App\Http\Requests\Admin\Question;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quiz_id' => ['sometimes', 'exists:quizzes,id'],
            'question_text' => ['sometimes', 'string'],
            'image_url' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:multiple_choice,true_false,short_answer'],
            'score' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
