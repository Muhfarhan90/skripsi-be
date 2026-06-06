<?php

namespace App\Http\Requests\Admin\AcademicPeriod;

use App\Http\Requests\Admin\AcademicPeriod\Concerns\ValidatesSingleActivePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAcademicPeriodRequest extends FormRequest
{
    use ValidatesSingleActivePeriod;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $period = $this->route('academic_period');
        $periodId = is_object($period) && method_exists($period, 'getKey')
            ? $period->getKey()
            : $period;

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('academic_periods', 'code')->ignore($periodId)],
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
        $period = $this->route('academic_period');
        $periodId = is_object($period) && method_exists($period, 'getKey')
            ? (int) $period->getKey()
            : (is_numeric($period) ? (int) $period : null);

        return [
            function (Validator $validator) use ($periodId) {
                $this->validateSingleActivePeriod($validator, $periodId);
            },
        ];
    }
}
