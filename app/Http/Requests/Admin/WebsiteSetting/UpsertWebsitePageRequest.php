<?php

namespace App\Http\Requests\Admin\WebsiteSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertWebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pageId = $this->route('website_page')?->id ?? $this->route('websitePage')?->id;

        return [
            'slug' => ['required', 'string', 'max:160', 'alpha_dash:ascii', Rule::unique('website_pages', 'slug')->ignore($pageId)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
