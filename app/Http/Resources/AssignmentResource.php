<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->section_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'due_at' => $this->due_at?->format('Y-m-d H:i:s'),
            'is_required_for_certificate' => (bool) $this->is_required_for_certificate,
            'allow_resubmission' => (bool) $this->allow_resubmission,
            'max_attempts' => $this->max_attempts,
            'status' => $this->status,
            'section' => $this->whenLoaded('section', function () {
                return [
                    'id' => $this->section?->id,
                    'course_id' => $this->section?->course_id,
                    'title' => $this->section?->title,
                ];
            }),
            'latest_submission' => $this->relationLoaded('latestSubmission')
                && $this->latestSubmission
                ? new AssignmentSubmissionResource($this->latestSubmission)
                : null,
            'submissions' => $this->whenLoaded('submissions', function () {
                return AssignmentSubmissionResource::collection($this->submissions);
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
