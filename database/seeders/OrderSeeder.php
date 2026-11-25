<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class OrderSeeder extends Seeder
{
    public function __construct(protected OrderService $orderService)
    {
    }

    public function run(): void
    {
        if (Order::exists()) {
            $this->command?->info('Orders already exist, skipping OrderSeeder.');

            return;
        }

        $customers = User::where('role', UserRole::CUSTOMER)->get();

        if ($customers->isEmpty()) {
            $this->command?->warn('No customers found. Run the user seeders first.');

            return;
        }

        $flows = [
            [],
            [OrderStatus::PAID],
            [OrderStatus::PAID, OrderStatus::SHIPPED],
            [OrderStatus::CANCELLED],
        ];

        $created = 0;

        foreach ($customers as $customer) {
            for ($i = 0; $i < rand(1, 3); $i++) {
                $products = Product::active()
                    ->where('stock_quantity', '>', 2)
                    ->inRandomOrder()
                    ->limit(rand(1, 3))
                    ->get();

                if ($products->isEmpty()) {
                    break 2;
                }

                $items = $products->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'quantity' => rand(1, 2),
                ])->all();

                try {
                    $order = $this->orderService->place($customer, $items);
                } catch (ValidationException) {
                    continue;
                }

                $flow = $flows[array_rand($flows)];

                foreach ($flow as $status) {
                    $order = $this->orderService->updateStatus($order, $status);
                }

                if ($flow) {
                    $placedAt = now()->subDays(rand(1, 20))->subMinutes(rand(0, 1439));
                    $order->timestamps = false;
                    $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();
                }

                $created++;
            }
        }

        $this->command?->info("Created {$created} orders.");
    }
}
