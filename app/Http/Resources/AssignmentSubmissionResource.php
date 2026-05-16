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
            'submitted_at' => $this->submitted_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'reviewed_at' => $this->reviewed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
