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
            'instructor_id' => $this->instructor_id,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'thumbnail' => $this->thumbnail,
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
                    ];
                }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
