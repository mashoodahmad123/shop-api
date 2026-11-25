<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Console\Kernel;
use App\Services\OrderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CancelExpiredOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrderAt(Product $product, int $quantity, \DateTimeInterface $at): Order
    {
        $this->travelTo($at);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => $quantity]]);
        $this->travelBack();

        return $order;
    }

    public function test_cancels_old_pending_orders_and_restores_stock(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $old = $this->placeOrderAt($product, 3, now()->subMinutes(31));
        $fresh = $this->placeOrderAt($product, 2, now()->subMinutes(10));
        $paid = $this->placeOrderAt($product, 1, now()->subHours(2));
        app(OrderService::class)->updateStatus($paid, OrderStatus::PAID);

        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->artisan('orders:cancel-expired')
            ->expectsOutputToContain('Cancelled 1 pending order(s)')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::CANCELLED, $old->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $fresh->fresh()->status);
        $this->assertSame(OrderStatus::PAID, $paid->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_running_twice_does_not_restock_twice(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->placeOrderAt($product, 5, now()->subHour());

        $this->artisan('orders:cancel-expired')->assertSuccessful();
        $this->artisan('orders:cancel-expired')->expectsOutputToContain('Cancelled 0')->assertSuccessful();

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_command_is_registered_in_scheduler(): void
    {
        $schedule = new Schedule();
        $kernel = $this->app->make(Kernel::class);

        (function () use ($schedule) {
            $this->schedule($schedule);
        })->call($kernel);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'orders:cancel-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
