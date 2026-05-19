<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'actor_id' => $this->actor_id,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'sent_at' => $this->sent_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'actor' => $this->whenLoaded('actor', function () {
                if (! $this->actor) {
                    return null;
                }

                return [
                    'id' => $this->actor->id,
                    'fullname' => $this->actor->fullname,
                    'email' => $this->actor->email,
                ];
            }),
        ];
    }
}
