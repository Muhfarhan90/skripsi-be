<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'start_at' => $this->start_at?->format('Y-m-d H:i:s'),
            'end_at' => $this->end_at?->format('Y-m-d H:i:s'),
            'enrollment_open_at' => $this->enrollment_open_at?->format('Y-m-d H:i:s'),
            'enrollment_close_at' => $this->enrollment_close_at?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'course_offerings_count' => isset($this->course_offerings_count)
                ? (int) $this->course_offerings_count
                : null,
        ];
    }
}
