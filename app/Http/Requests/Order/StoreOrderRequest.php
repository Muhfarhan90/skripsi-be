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
            'course_offering_id' => ['required_without:course_id', 'integer', 'exists:course_offerings,id'],
            'course_id' => ['required_without:course_offering_id', 'integer', 'exists:courses,id'],
            'payment_method' => ['nullable', 'string', 'in:manual,gateway'],
            'voucher_code' => ['nullable', 'string', 'exists:vouchers,code'],
            'note' => ['nullable', 'string'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'string', 'max:255'],
        ];
    }
}
