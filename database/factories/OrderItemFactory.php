<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->randomFloat(2, 50, 2000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes) => Product::find($attributes['product_id'])?->name ?? 'Product',
            'product_sku' => fn (array $attributes) => Product::find($attributes['product_id'])?->sku ?? 'SKU',
            'unit_price' => $price,
            'quantity' => $quantity,
            'line_total' => round($price * $quantity, 2),
        ];
    }
}
