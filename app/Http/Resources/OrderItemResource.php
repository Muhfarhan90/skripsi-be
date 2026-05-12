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

        if ($this->relationLoaded('courseOffering') && $this->courseOffering) {
            $courseId = $this->courseOffering->course_id;
            if ($this->courseOffering->relationLoaded('course')) {
                $course = $this->courseOffering->course;
            }
        }

        return [
            'course_id' => $courseId,
            'course_offering_id' => $this->course_offering_id,
            'price' => (float) $this->price,
            'course' => $course ? new CourseResource($course) : null,
            'course_offering' => new CourseOfferingResource($this->whenLoaded('courseOffering')),
        ];
    }
}
