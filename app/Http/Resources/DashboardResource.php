<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'stats' => [
                'total' => (int) $this->resource['total'],
                'customers' => (int) $this->resource['customers'],
                'admins' => (int) $this->resource['admins'],
                'this_week' => (int) $this->resource['this_week'],
            ],
        ];
    }
}
