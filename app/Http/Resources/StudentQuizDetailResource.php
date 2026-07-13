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
            'question_limit' => $this->question_limit,
            'max_attempts' => $this->max_attempts,
            'is_supplemental' => (bool) ($this->is_supplemental ?? false),
            'counts_toward_progress' => (bool) ($this->counts_toward_progress ?? true),
            'counts_toward_certificate' => (bool) ($this->counts_toward_certificate ?? true),
            'source' => $this->source,
            'is_locked' => (bool) ($this->is_locked ?? false),
            'is_new' => (bool) ($this->is_new ?? false),
            'is_completed' => (bool) ($this->is_completed ?? false),
            'questions' => StudentQuizQuestionResource::collection($this->whenLoaded('questions')),
            'is_supported' => count($this->unsupportedQuestionTypes) === 0,
            'unsupported_question_types' => array_values($this->unsupportedQuestionTypes),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
