<?php

namespace App\Http\Requests\Admin\Option;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'exists:questions,id'],
            'option_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'is_correct' => ['required', 'boolean'],
        ];
    }
}
