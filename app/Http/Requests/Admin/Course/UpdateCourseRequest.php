<?php

namespace App\Http\Requests\Admin\Course;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:courses,slug,' . $this->route('course'),
            'description' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'instructor_id' => 'sometimes|required|exists:users,id',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'thumbnail' => 'nullable|image|max:2048',
            'status' => 'sometimes|required|in:draft,published,archived',
            'requirements' => 'nullable|string',
            'outcomes' => 'nullable|string',
        ];
    }
}
