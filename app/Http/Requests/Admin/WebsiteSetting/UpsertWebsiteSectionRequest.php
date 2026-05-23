<?php

namespace App\Http\Requests\Admin\WebsiteSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertWebsiteSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sectionId = $this->route('website_section')?->id ?? $this->route('websiteSection')?->id;

        return [
            'page_key' => ['required', 'string', 'max:80'],
            'section_key' => [
                'required',
                'string',
                'max:80',
                'alpha_dash:ascii',
                Rule::unique('website_sections', 'section_key')
                    ->where('page_key', (string) $this->input('page_key'))
                    ->ignore($sectionId),
            ],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'secondary_cta_label' => ['nullable', 'string', 'max:120'],
            'secondary_cta_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:20'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.icon' => ['nullable', 'string', 'max:80'],
            'items.*.url' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'items.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
