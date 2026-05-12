<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $course = $this->relationLoaded('course') ? $this->course : null;
        $academicPeriod = $this->relationLoaded('academicPeriod') ? $this->academicPeriod : null;

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'academic_period_id' => $this->academic_period_id,
            'title' => $this->title,
            'start_at' => $this->start_at?->format('Y-m-d H:i:s'),
            'end_at' => $this->end_at?->format('Y-m-d H:i:s'),
            'enrollment_open_at' => $this->enrollment_open_at?->format('Y-m-d H:i:s'),
            'enrollment_close_at' => $this->enrollment_close_at?->format('Y-m-d H:i:s'),
            'capacity' => $this->capacity,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'status' => $this->status,
            'enrollments_count' => isset($this->enrollments_count)
                ? (int) $this->enrollments_count
                : null,
            'course' => $course ? [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'category' => $course->relationLoaded('category') && $course->category
                    ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                    ]
                    : null,
            ] : null,
            'academic_period' => $academicPeriod ? [
                'id' => $academicPeriod->id,
                'code' => $academicPeriod->code,
                'name' => $academicPeriod->name,
                'start_at' => $academicPeriod->start_at?->format('Y-m-d H:i:s'),
                'end_at' => $academicPeriod->end_at?->format('Y-m-d H:i:s'),
                'enrollment_open_at' => $academicPeriod->enrollment_open_at?->format('Y-m-d H:i:s'),
                'enrollment_close_at' => $academicPeriod->enrollment_close_at?->format('Y-m-d H:i:s'),
                'status' => $academicPeriod->status,
            ] : null,
        ];
    }
}
