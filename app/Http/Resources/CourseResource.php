<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catalogOffering = $this->relationLoaded('courseOfferings')
            ? $this->courseOfferings
                ->sortBy(function ($offering) {
                    return $offering->academicPeriod?->start_at?->getTimestamp() ?? PHP_INT_MAX;
                })
                ->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name : null,
            'instructor_id' => $this->instructor_id,
            'instructor_name' => $this->relationLoaded('instructor') ? $this->instructor?->fullname : null,
            'instructor_bio' => $this->relationLoaded('instructor') ? $this->instructor?->bio : null,
            'instructor_avatar' => $this->relationLoaded('instructor') ? $this->instructor?->avatar : null,
            'course_offering_id' => $catalogOffering?->id,
            'price' => $catalogOffering?->price,
            'discount_price' => $catalogOffering?->discount_price,
            'reviews_count' => isset($this->reviews_count) ? (int) $this->reviews_count : 0,
            'reviews_avg_rating' => isset($this->reviews_avg_rating)
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'thumbnail' => $this->thumbnail,
            'status' => $catalogOffering ? 'Tersedia' : 'Tidak tersedia',
            'skills' => $this->relationLoaded('skills')
                ? $this->skills->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'slug' => $skill->slug,
                ])->values()
                : [],
            'requirements' => $this->requirements,
            'outcomes' => $this->outcomes,
            'sections' => $this->relationLoaded('sections')
                ? $this->sections
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'course_id' => $section->course_id,
                            'title' => $section->title,
                            'sort_order' => $section->sort_order,
                            'lessons' => $section->relationLoaded('lessons')
                                ? $section->lessons
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
                                            'is_preview' => (bool) $lesson->is_preview,
                                        ];
                                    })
                                : [],
                            'quizzes' => $section->relationLoaded('quizzes')
                                ? $section->quizzes
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
                                        ];
                                    })
                                : [],
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
                                            'title' => $assignment->title,
                                            'description' => $assignment->description,
                                            'instructions' => $assignment->instructions,
                                            'is_required_for_certificate' => (bool) $assignment->is_required_for_certificate,
                                            'allow_resubmission' => (bool) $assignment->allow_resubmission,
                                            'max_attempts' => $assignment->max_attempts,
                                            'status' => $assignment->status,
                                        ];
                                    })
                                : [],
                        ];
                    })
                : [],
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
