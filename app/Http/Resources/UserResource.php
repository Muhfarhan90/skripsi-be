<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'role_id' => $this->role_id,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'nisn' => $this->nisn,
            'phone' => $this->phone,
            'address' => $this->address,
            'avatar' => $this->avatar,
            'gender' => $this->gender,
            'bio' => $this->bio,
            'date_of_birth' => $this->date_of_birth,
            'school_origin' => $this->school_origin,
            'is_active' => $this->is_active,
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
