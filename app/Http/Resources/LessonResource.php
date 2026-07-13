<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'lesson_url' => $this->lesson_url,
            'duration' => $this->duration,
            'sort_order' => $this->sort_order,
            'is_preview' => $this->is_preview,
            'status' => $this->status,
            'is_supplemental' => (bool) ($this->is_supplemental ?? false),
            'counts_toward_progress' => (bool) ($this->counts_toward_progress ?? true),
            'counts_toward_certificate' => (bool) ($this->counts_toward_certificate ?? true),
            'source' => $this->source,
            'is_locked' => (bool) ($this->is_locked ?? false),
            'is_new' => (bool) ($this->is_new ?? false),
            'is_completed' => (bool) ($this->is_completed ?? false),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
