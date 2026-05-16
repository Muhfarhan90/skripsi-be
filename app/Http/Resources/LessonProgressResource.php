<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'lesson_id' => $this->lesson_id,
            'progress_seconds' => $this->progress_seconds,
            'last_accessed_at' => $this->last_accessed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
