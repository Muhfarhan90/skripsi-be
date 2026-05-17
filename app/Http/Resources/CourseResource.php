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
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
