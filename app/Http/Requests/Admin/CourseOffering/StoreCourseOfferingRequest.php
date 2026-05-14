<?php

namespace App\Http\Requests\Admin\CourseOffering;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                'exists:courses,id',
                Rule::unique('course_offerings')->where(function ($query) {
                    return $query->where('academic_period_id', (int) $this->input('academic_period_id'));
                }),
            ],
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'title' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.unique' => 'Course ini sudah memiliki offering pada academic period yang dipilih.',
        ];
    }
}
