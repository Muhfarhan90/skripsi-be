<?php

namespace App\Http\Requests\Admin\AcademicPeriod;

use Illuminate\Foundation\Http\FormRequest;
class StoreAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:academic_periods,code'],
            'name' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'enrollment_open_at' => ['required', 'date'],
            'enrollment_close_at' => ['required', 'date', 'after:enrollment_open_at'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
