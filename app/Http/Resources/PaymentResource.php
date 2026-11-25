<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'result' => $this->result?->value,
            'note' => $this->note,
            'order_status' => $this->whenLoaded('order', fn () => $this->order->status?->value),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
