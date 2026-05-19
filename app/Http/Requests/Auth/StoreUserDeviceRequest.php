<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', Rule::in(['web', 'android', 'ios'])],
            'fcm_token' => ['required', 'string', 'max:4096'],
            'device_info' => ['nullable', 'array'],
        ];
    }
}
