<?php

namespace App\Http\Requests\Admin\Voucher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:255', 'unique:vouchers,code,' . $this->route('voucher')],
            'discount_type' => ['sometimes', 'in:percentage,fixed'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'expired_at' => ['nullable', 'date'],
        ];
    }
}
