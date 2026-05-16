<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferingEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $assignmentRequirement = $this->getAttribute('assignment_requirement');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_offering_id' => $this->course_offering_id,
            'order_id' => $this->order_id,
            'last_lesson_id' => $this->last_lesson_id,
            'progress' => $this->progress,
            'status' => $this->status,
            'user' => $user ? [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'email' => $user->email,
            ] : null,
            'assignment_requirement' => is_array($assignmentRequirement)
                ? [
                    'required_assignments' => (int) ($assignmentRequirement['required_assignments'] ?? 0),
                    'approved_assignments' => (int) ($assignmentRequirement['approved_assignments'] ?? 0),
                    'is_satisfied' => (bool) ($assignmentRequirement['is_satisfied'] ?? false),
                ]
                : null,
            'has_certificate' => $this->relationLoaded('certificate')
                ? $this->certificate !== null
                : $this->certificate()->exists(),
            'started_at' => $this->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ended_at' => $this->ended_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'expired_at' => ($this->ended_at ?? $this->expired_at)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
