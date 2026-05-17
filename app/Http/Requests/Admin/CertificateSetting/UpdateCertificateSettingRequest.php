<?php

namespace App\Http\Requests\Admin\CertificateSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'certificate_title' => ['required', 'string', 'max:255'],
            'certificate_prefix' => ['required', 'string', 'max:30'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'signature_image' => ['nullable', 'string', 'max:255'],
            'background_image' => ['nullable', 'string', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:1000'],
            'expires_after_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }
}
