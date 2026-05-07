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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name : null,
            'instructor_id' => $this->instructor_id,
            'instructor_name' => $this->relationLoaded('instructor') ? $this->instructor?->fullname : null,
            'price' => $this->price !== null ? (float) $this->price : null,
            'discount_price' => $this->discount_price !== null && (float) $this->discount_price > 0
                ? (float) $this->discount_price
                : null,
            'thumbnail' => $this->thumbnail,
            'status' => $this->status,
            'requirements' => $this->requirements,
            'outcomes' => $this->outcomes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
