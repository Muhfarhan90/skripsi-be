<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'quiz_id' => $this->quiz_id,
            'total_score' => $this->total_score,
            'status' => $this->status,
            'started_at' => $this->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'submitted_at' => $this->submitted_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'answers' => QuizAnswerResource::collection($this->whenLoaded('answers')),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
