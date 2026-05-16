<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCurriculumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name : null,
            'instructor_id' => $this->instructor_id,
            'instructor_name' => $this->relationLoaded('instructor') ? $this->instructor?->fullname : null,
            'thumbnail' => $this->thumbnail,
            'skills' => $this->relationLoaded('skills')
                ? $this->skills->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'slug' => $skill->slug,
                ])->values()
                : [],
            'status' => $this->status,
            'requirements' => $this->requirements,
            'outcomes' => $this->outcomes,
            'sections' => $this->sections
                ->sortBy('sort_order')
                ->values()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'course_id' => $section->course_id,
                        'title' => $section->title,
                        'sort_order' => $section->sort_order,
                        'lessons' => $section->lessons
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function ($lesson) {
                                return [
                                    'id' => $lesson->id,
                                    'section_id' => $lesson->section_id,
                                    'title' => $lesson->title,
                                    'description' => $lesson->description,
                                    'type' => $lesson->type,
                                    'lesson_url' => $lesson->lesson_url,
                                    'duration' => $lesson->duration,
                                    'sort_order' => $lesson->sort_order,
                                    'is_preview' => $lesson->is_preview,
                                ];
                            }),
                        'quizzes' => $section->quizzes
                            ->sortByDesc('id')
                            ->values()
                            ->map(function ($quiz) {
                                return [
                                    'id' => $quiz->id,
                                    'course_id' => $quiz->course_id,
                                    'section_id' => $quiz->section_id,
                                    'title' => $quiz->title,
                                    'description' => $quiz->description,
                                    'duration' => $quiz->duration,
                                    'passing_score' => $quiz->passing_score,
                                    'weight' => $quiz->weight,
                                    'is_active' => $quiz->is_active,
                                    'is_random' => $quiz->is_random,
                                    'max_attempts' => $quiz->max_attempts,
                                    'open_at' => $quiz->open_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                    'close_at' => $quiz->close_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                    'created_at' => $quiz->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                    'updated_at' => $quiz->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                ];
                            }),
                        'assignments' => $section->relationLoaded('assignments')
                            ? $section->assignments
                                ->sortBy(function ($assignment) {
                                    return $assignment->due_at?->getTimestamp() ?? PHP_INT_MAX;
                                })
                                ->values()
                                ->map(function ($assignment) {
                                    return [
                                        'id' => $assignment->id,
                                        'course_id' => $assignment->course_id,
                                        'section_id' => $assignment->section_id,
                                        'created_by' => $assignment->created_by,
                                        'title' => $assignment->title,
                                        'description' => $assignment->description,
                                        'instructions' => $assignment->instructions,
                                        'due_at' => $assignment->due_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                        'is_required_for_certificate' => (bool) $assignment->is_required_for_certificate,
                                        'allow_resubmission' => (bool) $assignment->allow_resubmission,
                                        'max_attempts' => $assignment->max_attempts,
                                        'status' => $assignment->status,
                                        'created_at' => $assignment->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                        'updated_at' => $assignment->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                    ];
                                })
                            : [],
                    ];
                }),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
