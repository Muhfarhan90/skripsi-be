<?php

namespace App\Http\Requests\Admin\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'transaction_id' => ['nullable', 'exists:transactions,id'],
        ];
    }
}
