<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => Str::title($this->faker->unique()->words(3, true)),
            'sku' => 'SKU-' . Str::upper(Str::random(8)),
            'price' => $this->faker->randomFloat(2, 50, 5000),
            'stock_quantity' => $this->faker->numberBetween(5, 100),
            'status' => ProductStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::INACTIVE]);
    }
}
