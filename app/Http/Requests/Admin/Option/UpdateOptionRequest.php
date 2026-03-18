<?php

    namespace App\Http\Requests\Admin\Option;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

    class UpdateOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['sometimes', 'exists:questions,id'],
            'option_text' => ['sometimes', 'string'],
            'image_url' => ['nullable', 'string'],
            'is_correct' => ['sometimes', 'boolean'],
        ];
    }
}
