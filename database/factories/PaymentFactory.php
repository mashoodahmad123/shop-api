<?php

namespace Database\Factories;

use App\Enums\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $transactionId = 'txn_' . Str::lower(Str::random(16));

        return [
            'order_id' => Order::factory(),
            'transaction_id' => $transactionId,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'currency' => 'INR',
            'status' => PaymentStatus::SUCCEEDED,
            'result' => PaymentResult::APPLIED,
            'note' => null,
            'payload' => ['transaction_id' => $transactionId],
        ];
    }
}
