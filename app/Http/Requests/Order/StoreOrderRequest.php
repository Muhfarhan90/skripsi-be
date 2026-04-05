<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['exists:courses,id'],
            'payment_method' => ['nullable', 'string', 'in:manual,gateway'],
            'voucher_code' => ['nullable', 'string', 'exists:vouchers,code'],
            'note' => ['nullable', 'string'],
        ];
    }
}
