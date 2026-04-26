<?php

namespace App\Http\Requests\Admin\Course;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertCourseCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course' => ['sometimes', 'array'],
            'course.title' => ['sometimes', 'string', 'max:255'],
            'course.description' => ['nullable', 'string'],
            'course.category_id' => ['sometimes', 'exists:categories,id'],
            'course.instructor_id' => ['sometimes', 'exists:users,id'],
            'course.price' => ['sometimes', 'numeric', 'min:0'],
            'course.discount_price' => ['nullable', 'numeric', 'min:0'],
            'course.status' => ['sometimes', 'in:draft,published,archived'],
            'course.requirements' => ['nullable', 'string'],
            'course.outcomes' => ['nullable', 'string'],

            'sections' => ['sometimes', 'array'],
            'sections.*.id' => ['sometimes', 'integer', 'exists:sections,id'],
            'sections.*.title' => ['required_with:sections', 'string', 'max:255'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'sections.*.lessons' => ['nullable', 'array'],

            'sections.*.lessons.*.id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'sections.*.lessons.*.title' => ['required_with:sections.*.lessons', 'string', 'max:255'],
            'sections.*.lessons.*.description' => ['nullable', 'string'],
            'sections.*.lessons.*.type' => ['required_with:sections.*.lessons', 'in:video,file,quiz'],
            'sections.*.lessons.*.lesson_url' => ['nullable', 'url'],
            'sections.*.lessons.*.duration' => ['nullable', 'integer', 'min:0'],
            'sections.*.lessons.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'sections.*.lessons.*.is_preview' => ['nullable', 'boolean'],
        ];
    }
}
