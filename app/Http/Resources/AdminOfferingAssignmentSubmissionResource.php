<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferingAssignmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignment = $this->relationLoaded('assignment') ? $this->assignment : null;
        $section = $assignment && $assignment->relationLoaded('section') ? $assignment->section : null;
        $user = $this->relationLoaded('user') ? $this->user : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;
        $enrollment = $this->relationLoaded('enrollment') ? $this->enrollment : null;

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
            'assignment' => $assignment ? [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'section_id' => $assignment->section_id,
                'section_title' => $section?->title,
            ] : null,
            'user' => $user ? [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'email' => $user->email,
            ] : null,
            'reviewer_name' => $reviewer?->fullname,
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'progress' => $enrollment->progress,
                'course_offering_id' => $enrollment->course_offering_id,
            ] : null,
            'submitted_at' => $this->submitted_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'reviewed_at' => $this->reviewed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
