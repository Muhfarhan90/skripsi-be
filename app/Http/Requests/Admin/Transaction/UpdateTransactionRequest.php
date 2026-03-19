<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
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
            'user_id' => ['sometimes', 'exists:users,id'],
            'course_id' => ['sometimes', 'exists:courses,id'],
            'voucher_id' => ['nullable', 'exists:vouchers,id'],
            'invoice_number' => ['sometimes', 'string', 'max:255', 'unique:transactions,invoice_number'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
            'admin_fee' => ['sometimes', 'numeric', 'min:0'],
            'grand_total' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:pending,paid,failed,refunded'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
            'verified_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
