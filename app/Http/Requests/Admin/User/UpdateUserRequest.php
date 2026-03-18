<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => 'sometimes|required|exists:roles,id',
            'fullname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $this->route('user'),
            'password' => 'nullable|string|min:8|confirmed',
            'nisn' => 'nullable|string|max:20|unique:users,nisn,' . $this->route('user'),
            'is_active' => 'nullable|boolean',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'avatar' => 'nullable|string|max:255',
            'school_origin' => 'nullable|string|max:255',
            'gender' => 'nullable|in:laki-laki,perempuan',
            'bio' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ];
    }
}
