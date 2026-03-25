<?php

namespace App\Http\Requests\Admin\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,completed,expired,cancelled'],
        ];
    }
}
