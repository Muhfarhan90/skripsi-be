<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'string', 'max:255'],
        ];
    }
}
