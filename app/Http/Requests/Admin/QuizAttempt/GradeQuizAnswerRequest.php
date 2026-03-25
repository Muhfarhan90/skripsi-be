<?php

namespace App\Http\Requests\Admin\QuizAttempt;

use Illuminate\Foundation\Http\FormRequest;

class GradeQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_correct' => ['required', 'boolean'],
            'score' => ['required', 'integer', 'min:0'],
        ];
    }
}
