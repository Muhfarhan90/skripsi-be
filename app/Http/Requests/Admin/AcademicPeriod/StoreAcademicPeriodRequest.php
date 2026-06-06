<?php

namespace App\Http\Requests\Admin\AcademicPeriod;

use App\Http\Requests\Admin\AcademicPeriod\Concerns\ValidatesSingleActivePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAcademicPeriodRequest extends FormRequest
{
    use ValidatesSingleActivePeriod;

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

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validateSingleActivePeriod($validator);
            },
        ];
    }
}
