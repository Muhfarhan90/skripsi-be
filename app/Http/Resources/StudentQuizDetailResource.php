<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentQuizDetailResource extends JsonResource
{
    /**
     * @param  array<int, string>  $unsupportedQuestionTypes
     */
    public function __construct($resource, protected array $unsupportedQuestionTypes = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'duration' => $this->duration,
            'passing_score' => $this->passing_score,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'is_random' => $this->is_random,
            'max_attempts' => $this->max_attempts,
            'open_at' => $this->open_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'close_at' => $this->close_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'questions' => StudentQuizQuestionResource::collection($this->whenLoaded('questions')),
            'is_supported' => count($this->unsupportedQuestionTypes) === 0,
            'unsupported_question_types' => array_values($this->unsupportedQuestionTypes),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
