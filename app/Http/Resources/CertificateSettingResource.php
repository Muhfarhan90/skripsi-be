<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_name' => $this->organization_name,
            'certificate_title' => $this->certificate_title,
            'certificate_prefix' => $this->certificate_prefix,
            'signatory_name' => $this->signatory_name,
            'signatory_title' => $this->signatory_title,
            'signature_image' => $this->signature_image,
            'background_image' => $this->background_image,
            'footer_note' => $this->footer_note,
            'expires_after_months' => $this->expires_after_months,
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
