<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $courseId = null;
        $course = null;
        $snapshot = is_array($this->course_offering_snapshot)
            ? $this->course_offering_snapshot
            : [];

        if ($this->relationLoaded('courseOffering') && $this->courseOffering) {
            $courseId = $this->courseOffering->course_id;
            if ($this->courseOffering->relationLoaded('course')) {
                $course = $this->courseOffering->course;
            }
        }

        $courseId ??= $snapshot['course_id'] ?? null;

        return [
            'course_id' => $courseId,
            'course_offering_id' => $this->course_offering_id,
            'course_title' => $this->course_title ?? $snapshot['course_title'] ?? null,
            'course_slug' => $this->course_slug ?? $snapshot['course_slug'] ?? null,
            'period_code' => $this->period_code ?? $snapshot['period_code'] ?? null,
            'period_name' => $this->period_name ?? $snapshot['period_name'] ?? null,
            'price' => (float) $this->price,
            'course' => $course
                ? new CourseResource($course)
                : $this->snapshotCourse($snapshot),
            'course_snapshot' => $this->snapshotCourse($snapshot),
            'course_offering_snapshot' => $snapshot ?: null,
            'course_offering' => new CourseOfferingResource($this->whenLoaded('courseOffering')),
        ];
    }

    private function snapshotCourse(array $snapshot): ?array
    {
        if (! isset($snapshot['course_id']) && ! isset($snapshot['course_title'])) {
            return null;
        }

        return [
            'id' => $snapshot['course_id'] ?? null,
            'title' => $this->course_title ?? $snapshot['course_title'] ?? null,
            'slug' => $this->course_slug ?? $snapshot['course_slug'] ?? null,
            'category' => isset($snapshot['category_id']) || isset($snapshot['category_name'])
                ? [
                    'id' => $snapshot['category_id'] ?? null,
                    'name' => $snapshot['category_name'] ?? null,
                ]
                : null,
            'instructor' => isset($snapshot['instructor_id']) || isset($snapshot['instructor_name'])
                ? [
                    'id' => $snapshot['instructor_id'] ?? null,
                    'fullname' => $snapshot['instructor_name'] ?? null,
                ]
                : null,
        ];
    }
}
