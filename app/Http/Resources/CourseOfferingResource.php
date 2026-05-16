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
            'capacity' => $this->capacity,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'is_active' => (bool) $this->is_active,
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
                'instructor' => $course->relationLoaded('instructor') && $course->instructor
                    ? [
                        'id' => $course->instructor->id,
                        'fullname' => $course->instructor->fullname,
                    ]
                    : null,
            ] : null,
            'academic_period' => $academicPeriod ? [
                'id' => $academicPeriod->id,
                'code' => $academicPeriod->code,
                'name' => $academicPeriod->name,
                'start_at' => $academicPeriod->start_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'end_at' => $academicPeriod->end_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'enrollment_open_at' => $academicPeriod->enrollment_open_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'enrollment_close_at' => $academicPeriod->enrollment_close_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'is_active' => (bool) $academicPeriod->is_active,
            ] : null,
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
