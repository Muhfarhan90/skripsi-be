<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question_text' => $this->question_text,
            'image_url' => $this->image_url,
            'type' => $this->type,
            'score' => $this->score,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'options' => OptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
