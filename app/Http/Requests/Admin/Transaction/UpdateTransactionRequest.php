<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['sometimes', 'exists:orders,id'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:pending,success,failed'],
            'payment_method' => ['nullable', 'string', 'in:gateway'],
            'payment_channel' => ['nullable', 'string', 'max:255'],
            'payment_url' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
        ];
    }
}
