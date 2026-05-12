<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_text' => ['nullable', 'string'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
