<?php

namespace App\Http\Requests\QuizAttempt;

use Illuminate\Foundation\Http\FormRequest;

class UpsertQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_option_id' => ['nullable', 'exists:options,id', 'required_without:answer_text'],
            'answer_text' => ['nullable', 'string', 'required_without:selected_option_id'],
        ];
    }
}
