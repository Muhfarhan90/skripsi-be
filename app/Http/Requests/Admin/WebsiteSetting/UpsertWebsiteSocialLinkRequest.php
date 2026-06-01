<?php

namespace App\Http\Requests\Admin\WebsiteSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpsertWebsiteSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
