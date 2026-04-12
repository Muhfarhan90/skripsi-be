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
            'issued_at' => $this->issued_at?->format('Y-m-d H:i:s'),
            'expired_at' => $this->expired_at?->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
