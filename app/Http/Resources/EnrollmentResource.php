<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'transaction_id' => $this->transaction_id,
            'last_lesson_id' => $this->last_lesson_id,
            'progress' => $this->progress,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'expired_at' => $this->expired_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
