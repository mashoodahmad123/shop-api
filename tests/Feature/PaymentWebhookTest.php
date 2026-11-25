<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.webhook_secret' => $this->secret]);
    }

    private function sendWebhook(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, $this->secret);

        return $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    private function payload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'transaction_id' => 'txn_123',
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'INR',
            'status' => 'succeeded',
        ], $overrides);
    }

    public function test_valid_webhook_marks_order_paid(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order))
            ->assertOk()
            ->assertJsonPath('message', 'Webhook processed')
            ->assertJsonPath('data.result', 'applied')
            ->assertJsonPath('data.order_status', 'paid');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertDatabaseHas('payments', ['transaction_id' => 'txn_123', 'order_id' => $order->id]);
    }

    public function test_sha256_prefixed_signature_is_accepted(): void
    {
        $order = Order::factory()->create(['total' => 100]);
        $payload = $this->payload($order);

        $this->sendWebhook($payload, 'sha256=' . hash_hmac('sha256', json_encode($payload), $this->secret))
            ->assertOk();
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order), hash_hmac('sha256', 'tampered', $this->secret))
            ->assertUnauthorized()
            ->assertExactJson(['status' => false, 'message' => 'Invalid signature.']);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_signature_made_with_other_secret_is_rejected(): void
    {
        $order = Order::factory()->create(['total' => 1500]);
        $payload = $this->payload($order);

        $this->sendWebhook($payload, hash_hmac('sha256', json_encode($payload), 'wrong-secret'))
            ->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order), '')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Missing signature.');
    }

    public function test_repeated_webhook_changes_nothing(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order))->assertOk();

        $order->update(['status' => OrderStatus::SHIPPED]);
        $paymentBefore = Payment::first()->toArray();
        $orderBefore = $order->fresh()->toArray();

        $this->sendWebhook($this->payload($order, ['amount' => 1, 'status' => 'failed']))
            ->assertOk()
            ->assertJsonPath('message', 'Duplicate webhook ignored')
            ->assertJsonPath('data.result', 'applied');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($paymentBefore, Payment::first()->toArray());
        $this->assertSame($orderBefore, $order->fresh()->toArray());
    }

    public function test_amount_mismatch_does_not_mark_paid(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order, ['amount' => 10]))
            ->assertOk()
            ->assertJsonPath('data.result', 'rejected');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_failed_payment_does_not_mark_paid(): void
    {
        $order = Order::factory()->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order, ['status' => 'failed']))
            ->assertOk()
            ->assertJsonPath('data.result', 'ignored');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_payment_for_cancelled_order_is_recorded_but_ignored(): void
    {
        $order = Order::factory()->status(OrderStatus::CANCELLED)->create(['total' => 1500]);

        $this->sendWebhook($this->payload($order))
            ->assertOk()
            ->assertJsonPath('data.result', 'ignored');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_invalid_payload_returns_422(): void
    {
        $this->sendWebhook(['transaction_id' => 'txn_x'])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors(['order_id', 'amount', 'status']);
    }

    public function test_missing_secret_returns_500(): void
    {
        config(['services.payment.webhook_secret' => null]);
        $order = Order::factory()->create();

        $this->sendWebhook($this->payload($order), 'anything')->assertStatus(500);
    }
}
