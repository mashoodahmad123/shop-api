<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function listForCustomer(User $user, array $filters): LengthAwarePaginator
    {
        return $user->orders()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function listForAdmin(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with(['items', 'user'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function place(User $user, array $items): Order
    {
        $quantities = $this->mergeQuantities($items);

        $order = DB::transaction(function () use ($user, $quantities) {
            $products = $this->lockProducts(array_keys($quantities));

            $this->assertAvailable($products, $quantities);

            $totalCents = 0;
            $lines = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                $unitCents = $this->toCents($product->price);
                $lineCents = $unitCents * $quantity;
                $totalCents += $lineCents;

                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $this->fromCents($unitCents),
                    'quantity' => $quantity,
                    'line_total' => $this->fromCents($lineCents),
                ];

                Product::whereKey($product->id)->update([
                    'stock_quantity' => DB::raw('stock_quantity - ' . (int) $quantity),
                ]);
            }

            $order = $user->orders()->create([
                'status' => OrderStatus::PENDING,
                'total' => $this->fromCents($totalCents),
            ]);

            $order->items()->createMany($lines);

            return $order->load('items');
        });

        SendOrderConfirmationEmail::dispatch($order);

        return $order;
    }

    public function cancelByCustomer(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if (!$order->status->isCancellableByCustomer()) {
                throw new OrderException("Only pending orders can be cancelled. Current status: {$order->status->value}.");
            }

            $this->restock($order);
            $order->update(['status' => OrderStatus::CANCELLED]);

            return $order->load('items');
        });
    }

    public function cancelExpiredPending(int $minutes = 30): int
    {
        $cutoff = now()->subMinutes($minutes);
        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::PENDING->value)
            ->where('created_at', '<=', $cutoff)
            ->select('id')
            ->chunkById(100, function ($orders) use ($cutoff, &$cancelled) {
                foreach ($orders as $order) {
                    $done = DB::transaction(function () use ($order, $cutoff) {
                        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                        if (!$locked || $locked->status !== OrderStatus::PENDING || $locked->created_at->gt($cutoff)) {
                            return false;
                        }

                        $this->restock($locked);
                        $locked->update(['status' => OrderStatus::CANCELLED]);

                        return true;
                    });

                    if ($done) {
                        $cancelled++;
                    }
                }
            });

        return $cancelled;
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order = $this->lockOrder($order);

            if (!$order->status->canTransitionTo($status)) {
                throw new OrderException("Cannot change order status from {$order->status->value} to {$status->value}.");
            }

            if ($status === OrderStatus::CANCELLED) {
                $this->restock($order);
            }

            $order->update(['status' => $status]);

            return $order->load(['items', 'user']);
        });
    }

    protected function mergeQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
        }

        ksort($quantities);

        return $quantities;
    }

    protected function lockProducts(array $productIds): Collection
    {
        return Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    protected function lockOrder(Order $order): Order
    {
        return Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    protected function assertAvailable(Collection $products, array $quantities): void
    {
        $errors = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product || !$product->isActive()) {
                $errors["product_{$productId}"] = ["Product #{$productId} is not available."];
                continue;
            }

            if ($product->stock_quantity < $quantity) {
                $errors["product_{$productId}"] = [
                    "Insufficient stock for {$product->name}. Requested {$quantity}, available {$product->stock_quantity}.",
                ];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function restock(Order $order): void
    {
        $items = $order->items()->orderBy('product_id')->get();

        Product::withTrashed()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            Product::withTrashed()->whereKey($item->product_id)->update([
                'stock_quantity' => DB::raw('stock_quantity + ' . (int) $item->quantity),
            ]);
        }
    }

    protected function toCents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
