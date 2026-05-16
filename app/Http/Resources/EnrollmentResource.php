<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\CourseResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $courseId = null;
        $course = null;

        if ($this->relationLoaded('courseOffering') && $this->courseOffering) {
            $courseId = $this->courseOffering->course_id;
            if ($this->courseOffering->relationLoaded('course')) {
                $course = $this->courseOffering->course;
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $courseId,
            'course_offering_id' => $this->course_offering_id,
            'order_id' => $this->order_id,
            'last_lesson_id' => $this->last_lesson_id,
            'progress' => $this->progress,
            'status' => $this->status,
            'course' => $course ? new CourseResource($course) : null,
            'order' => new OrderResource($this->whenLoaded('order')),
            'started_at' => $this->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ended_at' => $this->ended_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'expired_at' => ($this->ended_at ?? $this->expired_at)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'has_certificate' => $this->relationLoaded('certificate')
                ? $this->certificate !== null
                : $this->certificate()->exists(),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
