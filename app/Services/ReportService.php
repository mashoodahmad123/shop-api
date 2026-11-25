<?php

namespace App\Services;

use App\Enums\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const SOLD_STATUSES = [
        OrderStatus::PAID,
        OrderStatus::SHIPPED,
    ];

    public function topProductsReport(string $from, string $to, int $limit = 5): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'counted_statuses' => array_map(fn (OrderStatus $s) => $s->value, self::SOLD_STATUSES),
            'products' => $this->topProductsByQuantity($from, $to, $limit),
        ];
    }

    public function topProductsByQuantity(string $from, string $to, int $limit = 5): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', array_map(fn (OrderStatus $s) => $s->value, self::SOLD_STATUSES))
            ->whereBetween('orders.created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'),
            ])
            ->orderByDesc('quantity_sold')
            ->orderBy('products.id')
            ->limit($limit)
            ->get();
    }
}
