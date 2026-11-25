<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function handleWebhook(array $data, array $payload): array
    {
        $existing = Payment::where('transaction_id', $data['transaction_id'])->first();

        if ($existing) {
            return [$existing, true];
        }

        try {
            return DB::transaction(function () use ($data, $payload) {
                $order = Order::query()->whereKey($data['order_id'])->lockForUpdate()->firstOrFail();

                $existing = Payment::where('transaction_id', $data['transaction_id'])->lockForUpdate()->first();

                if ($existing) {
                    return [$existing, true];
                }

                $status = PaymentStatus::from($data['status']);
                [$result, $note] = $this->decide($order, $status, $data['amount']);

                if ($result === PaymentResult::APPLIED) {
                    $order->update(['status' => OrderStatus::PAID]);
                }

                $payment = $order->payments()->create([
                    'transaction_id' => $data['transaction_id'],
                    'amount' => $data['amount'],
                    'currency' => strtoupper($data['currency'] ?? 'INR'),
                    'status' => $status,
                    'result' => $result,
                    'note' => $note,
                    'payload' => $payload,
                ]);

                return [$payment, false];
            });
        } catch (QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }

            return [Payment::where('transaction_id', $data['transaction_id'])->firstOrFail(), true];
        }
    }

    protected function decide(Order $order, PaymentStatus $status, string|float|int $amount): array
    {
        if ($status === PaymentStatus::FAILED) {
            return [PaymentResult::IGNORED, 'Payment failed at the provider.'];
        }

        if ($this->toCents($amount) !== $this->toCents($order->total)) {
            return [PaymentResult::REJECTED, "Amount mismatch: expected {$order->total}, received {$amount}."];
        }

        if ($order->status === OrderStatus::PENDING) {
            return [PaymentResult::APPLIED, null];
        }

        if ($order->status === OrderStatus::CANCELLED) {
            return [PaymentResult::IGNORED, 'Order is cancelled. Refund required.'];
        }

        return [PaymentResult::IGNORED, "Order is already {$order->status->value}."];
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[0] ?? null, ['23000', '23505'], true);
    }

    protected function toCents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
