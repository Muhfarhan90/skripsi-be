<?php

namespace App\Http\Requests\Admin\Order;

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
            'user_id' => ['required', 'exists:users,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['exists:courses,id'],
            'payment_method' => ['nullable', 'string', 'in:manual,gateway'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'voucher_code' => ['nullable', 'string', 'exists:vouchers,code'],
        ];
    }
}
