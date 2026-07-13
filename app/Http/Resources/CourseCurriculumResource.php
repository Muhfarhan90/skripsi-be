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
                ->values()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'course_id' => $section->course_id,
                        'title' => $section->title,
                        'sort_order' => $section->sort_order,
                        'is_supplemental' => (bool) ($section->is_supplemental ?? false),
                        'source' => $section->source,
                        'lessons' => $section->lessons
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
                                    'status' => $lesson->status,
                                    'is_supplemental' => (bool) ($lesson->is_supplemental ?? false),
                                    'counts_toward_progress' => (bool) ($lesson->counts_toward_progress ?? true),
                                    'counts_toward_certificate' => (bool) ($lesson->counts_toward_certificate ?? true),
                                    'source' => $lesson->source,
                                    'is_locked' => (bool) ($lesson->is_locked ?? false),
                                    'is_new' => (bool) ($lesson->is_new ?? false),
                                    'is_completed' => (bool) ($lesson->is_completed ?? false),
                                ];
                            }),
                        'quizzes' => $section->quizzes
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
                                    'question_limit' => $quiz->question_limit,
                                    'max_attempts' => $quiz->max_attempts,
                                    'is_supplemental' => (bool) ($quiz->is_supplemental ?? false),
                                    'counts_toward_progress' => (bool) ($quiz->counts_toward_progress ?? true),
                                    'counts_toward_certificate' => (bool) ($quiz->counts_toward_certificate ?? true),
                                    'source' => $quiz->source,
                                    'is_locked' => (bool) ($quiz->is_locked ?? false),
                                    'is_new' => (bool) ($quiz->is_new ?? false),
                                    'is_completed' => (bool) ($quiz->is_completed ?? false),
                                    'created_at' => $quiz->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                    'updated_at' => $quiz->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                                ];
                            }),
                        'assignments' => $section->relationLoaded('assignments')
                            ? $section->assignments
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
                                        'is_required_for_certificate' => (bool) $assignment->is_required_for_certificate,
                                        'allow_resubmission' => (bool) $assignment->allow_resubmission,
                                        'max_attempts' => $assignment->max_attempts,
                                        'status' => $assignment->status,
                                        'is_supplemental' => (bool) ($assignment->is_supplemental ?? false),
                                        'counts_toward_progress' => (bool) ($assignment->counts_toward_progress ?? true),
                                        'counts_toward_certificate' => (bool) ($assignment->counts_toward_certificate ?? true),
                                        'source' => $assignment->source,
                                        'is_locked' => (bool) ($assignment->is_locked ?? false),
                                        'is_new' => (bool) ($assignment->is_new ?? false),
                                        'is_completed' => (bool) ($assignment->is_completed ?? false),
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
