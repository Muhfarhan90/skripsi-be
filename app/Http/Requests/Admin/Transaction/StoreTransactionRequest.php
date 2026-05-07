<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,success,failed'],
            'payment_method' => ['nullable', 'string', 'in:manual,gateway'],
            'payment_channel' => ['nullable', 'string', 'max:255'],
            'payment_url' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
            'verified_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
