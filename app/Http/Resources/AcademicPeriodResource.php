<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $courseOfferings = $this->relationLoaded('courseOfferings') ? $this->courseOfferings : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'start_at' => $this->start_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'end_at' => $this->end_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'enrollment_open_at' => $this->enrollment_open_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'enrollment_close_at' => $this->enrollment_close_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'is_active' => (bool) $this->is_active,
            'course_offerings_count' => isset($this->course_offerings_count)
                ? (int) $this->course_offerings_count
                : null,
            'course_offerings' => $courseOfferings
                ? $courseOfferings->map(function ($offering) {
                    $course = $offering->relationLoaded('course') ? $offering->course : null;

                    return [
                        'id' => $offering->id,
                        'course_id' => $offering->course_id,
                        'academic_period_id' => $offering->academic_period_id,
                        'title' => $offering->title,
                        'capacity' => $offering->capacity,
                        'price' => $offering->price,
                        'discount_price' => $offering->discount_price,
                        'is_active' => (bool) $offering->is_active,
                        'enrollments_count' => isset($offering->enrollments_count)
                            ? (int) $offering->enrollments_count
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
                    ];
                })->values()
                : null,
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
