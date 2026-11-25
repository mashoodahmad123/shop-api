<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendOrderConfirmationEmail;
use App\Mail\OrderConfirmationMail;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderConfirmationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_order_dispatches_confirmation_job(): void
    {
        Queue::fake();

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create(['stock_quantity' => 5]);
        Sanctum::actingAs($customer);

        $orderId = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        Queue::assertPushed(SendOrderConfirmationEmail::class, function ($job) use ($orderId) {
            return $job->order->id === $orderId;
        });
    }

    public function test_failed_order_does_not_dispatch_job(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_job_sends_confirmation_email_to_customer(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create(['stock_quantity' => 5]);

        Queue::fake();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 2]]);

        (new SendOrderConfirmationEmail($order))->handle();

        Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($customer, $order) {
            return $mail->hasTo($customer->email) && $mail->order->is($order);
        });
    }

    public function test_job_retries_three_times(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create(['stock_quantity' => 5]);

        Queue::fake();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);

        $job = new SendOrderConfirmationEmail($order);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
        $this->assertTrue($job->afterCommit);
    }

    public function test_email_renders_order_details(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER, 'name' => 'Ravi Kumar']);
        $product = Product::factory()->create(['name' => 'Wireless Mouse', 'price' => 799.50, 'stock_quantity' => 5]);

        Queue::fake();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 2]]);

        $mail = new OrderConfirmationMail($order);

        $mail->assertHasSubject("Order #{$order->id} confirmed");
        $mail->assertSeeInHtml('Ravi Kumar');
        $mail->assertSeeInHtml('Wireless Mouse');
        $mail->assertSeeInHtml('1,599.00');
    }
}
