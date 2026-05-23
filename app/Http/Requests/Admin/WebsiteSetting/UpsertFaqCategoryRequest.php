<?php

namespace App\Http\Requests\Admin\WebsiteSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertFaqCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('faq_category')?->id ?? $this->route('faqCategory')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('faq_categories', 'name')->ignore($categoryId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
