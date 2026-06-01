<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_key' => $this->section_key,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'secondary_cta_label' => $this->secondary_cta_label,
            'secondary_cta_url' => $this->secondary_cta_url,
            'is_active' => $this->is_active,
            'items' => WebsiteSectionItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
