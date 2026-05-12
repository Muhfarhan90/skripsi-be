<?php

namespace App\Http\Requests\Admin\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAssignmentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,revision_required'],
            'review_notes' => ['nullable', 'string'],
        ];
    }
}
