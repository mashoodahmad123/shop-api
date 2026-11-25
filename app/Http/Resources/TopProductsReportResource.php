<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopProductsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'counted_statuses' => $this->resource['counted_statuses'],
            'products' => TopProductResource::collection($this->resource['products']),
        ];
    }
}
