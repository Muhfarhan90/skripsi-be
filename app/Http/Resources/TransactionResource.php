<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_code' => $this->invoice_code,
            'external_id' => $this->external_id,
            'payment_method' => $this->payment_method,
            'payment_channel' => $this->payment_channel,
            'payment_url' => $this->payment_url,
            'payment_reference' => $this->payment_reference,
            'payment_proof' => $this->payment_proof,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'expired_at' => $this->expired_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'verified_by' => $this->verified_by,
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'order' => new OrderResource($this->whenLoaded('order')),
        ];
    }
}
