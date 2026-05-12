<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_offering_id' => ['required_without:course_id', 'nullable', 'integer', 'exists:course_offerings,id'],
            'course_id' => ['required_without:course_offering_id', 'nullable', 'integer', 'exists:courses,id'],
        ];
    }
}
