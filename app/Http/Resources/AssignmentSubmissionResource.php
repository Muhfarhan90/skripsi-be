<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'enrollment_id' => $this->enrollment_id,
            'user_id' => $this->user_id,
            'attempt_no' => $this->attempt_no,
            'submission_text' => $this->submission_text,
            'attachment_url' => $this->attachment_url,
            'status' => $this->status,
            'review_notes' => $this->review_notes,
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->relationLoaded('reviewer') ? $this->reviewer?->fullname : null,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
