<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (int) $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity_sold' => (int) $this->quantity_sold,
            'orders_count' => (int) $this->orders_count,
            'revenue' => number_format((float) $this->revenue, 2, '.', ''),
        ];
    }
}
