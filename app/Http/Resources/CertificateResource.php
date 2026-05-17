<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'enrollment_id' => $this->enrollment_id,
            'certificate_number' => $this->certificate_number,
            'certificate_url' => $this->certificate_url,
            'status' => $this->status,
            'template_version' => $this->template_version,
            'verification_code' => $this->verification_code,
            'snapshot_data' => $this->snapshot_data,
            'issued_at' => $this->issued_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'expired_at' => $this->expired_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'revoked_at' => $this->revoked_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'revoked_reason' => $this->revoked_reason,
            'user' => new UserResource($this->whenLoaded('user')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
