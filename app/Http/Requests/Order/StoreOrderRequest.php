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
            'course_offering_ids' => ['required_without:course_ids', 'array', 'min:1'],
            'course_offering_ids.*' => ['integer', 'exists:course_offerings,id'],
            'course_ids' => ['required_without:course_offering_ids', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'payment_method' => ['nullable', 'string', 'in:manual,gateway'],
            'voucher_code' => ['nullable', 'string', 'exists:vouchers,code'],
            'note' => ['nullable', 'string'],
        ];
    }
}
