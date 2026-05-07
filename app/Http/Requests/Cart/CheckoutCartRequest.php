<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_code' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'payment_method' => ['required', 'string', 'in:manual'],
        ];
    }
}
