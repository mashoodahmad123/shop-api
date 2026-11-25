<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        Order::query()
            ->whereIn('status', [OrderStatus::PAID->value, OrderStatus::SHIPPED->value])
            ->whereDoesntHave('payments')
            ->each(function (Order $order) use (&$created) {
                $transactionId = 'txn_seed_' . Str::lower(Str::random(12));

                $order->payments()->create([
                    'transaction_id' => $transactionId,
                    'amount' => $order->total,
                    'currency' => 'INR',
                    'status' => PaymentStatus::SUCCEEDED,
                    'result' => PaymentResult::APPLIED,
                    'payload' => [
                        'transaction_id' => $transactionId,
                        'order_id' => $order->id,
                        'amount' => $order->total,
                        'status' => 'succeeded',
                    ],
                ]);

                $created++;
            });

        $this->command?->info("Created {$created} payments.");
    }
}
