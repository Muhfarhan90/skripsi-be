<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'voucher_id' => $this->voucher_id,
            'order_code' => $this->order_code,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'admin_fee' => (float) $this->admin_fee,
            'note' => $this->note,
            'grand_total' => (float) $this->grand_total,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
            'voucher' => new VoucherResource($this->whenLoaded('voucher')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
