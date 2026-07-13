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
            'is_required_for_certificate' => (bool) $this->is_required_for_certificate,
            'allow_resubmission' => (bool) $this->allow_resubmission,
            'max_attempts' => $this->max_attempts,
            'status' => $this->status,
            'is_supplemental' => (bool) ($this->is_supplemental ?? false),
            'counts_toward_progress' => (bool) ($this->counts_toward_progress ?? true),
            'counts_toward_certificate' => (bool) ($this->counts_toward_certificate ?? true),
            'source' => $this->source,
            'is_locked' => (bool) ($this->is_locked ?? false),
            'is_new' => (bool) ($this->is_new ?? false),
            'is_completed' => (bool) ($this->is_completed ?? false),
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
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
