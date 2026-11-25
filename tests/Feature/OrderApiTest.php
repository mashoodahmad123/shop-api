<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function placeOrder(array $items)
    {
        return $this->postJson('/api/orders', ['items' => $items]);
    }

    public function test_customer_can_place_order_with_multiple_products(): void
    {
        Sanctum::actingAs($this->customer());
        $mouse = Product::factory()->create(['price' => 799.50, 'stock_quantity' => 10]);
        $cable = Product::factory()->create(['price' => 120.25, 'stock_quantity' => 5]);

        $response = $this->placeOrder([
            ['product_id' => $mouse->id, 'quantity' => 2],
            ['product_id' => $cable->id, 'quantity' => 3],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '1959.75')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(8, $mouse->fresh()->stock_quantity);
        $this->assertSame(2, $cable->fresh()->stock_quantity);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $mouse->id,
            'unit_price' => '799.50',
            'quantity' => 2,
            'line_total' => '1599.00',
        ]);
    }

    public function test_order_keeps_prices_from_time_of_order(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'name' => 'Original']);

        $orderId = $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]])->json('data.id');

        $product->update(['price' => 999, 'name' => 'Renamed']);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.total', '100.00')
            ->assertJsonPath('data.items.0.unit_price', '100.00')
            ->assertJsonPath('data.items.0.product_name', 'Original');
    }

    public function test_placing_order_reduces_stock(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 4]])->assertCreated();

        $this->assertSame(6, $product->fresh()->stock_quantity);
    }

    public function test_ordering_more_than_stock_fails_with_422(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 4]])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors("product_{$product->id}");

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_insufficient_stock_fails_and_changes_nothing(): void
    {
        Sanctum::actingAs($this->customer());
        $ok = Product::factory()->create(['stock_quantity' => 10]);
        $low = Product::factory()->create(['stock_quantity' => 1]);

        $this->placeOrder([
            ['product_id' => $ok->id, 'quantity' => 2],
            ['product_id' => $low->id, 'quantity' => 2],
        ])->assertStatus(422)->assertJsonValidationErrors("product_{$low->id}");

        $this->assertSame(10, $ok->fresh()->stock_quantity);
        $this->assertSame(1, $low->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_last_item_can_only_be_bought_once(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);

        Sanctum::actingAs($this->customer());
        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

        Sanctum::actingAs($this->customer());
        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]])->assertStatus(422);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_inactive_or_deleted_products_cannot_be_ordered(): void
    {
        Sanctum::actingAs($this->customer());
        $inactive = Product::factory()->inactive()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        $this->placeOrder([['product_id' => $inactive->id, 'quantity' => 1]])
            ->assertStatus(422)->assertJsonValidationErrors("product_{$inactive->id}");

        $this->placeOrder([['product_id' => $deleted->id, 'quantity' => 1]])
            ->assertStatus(422)->assertJsonValidationErrors("product_{$deleted->id}");
    }

    public function test_order_validation(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->create();

        $this->placeOrder([])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 0]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');

        $this->placeOrder([['product_id' => 99999, 'quantity' => 1]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');

        $this->placeOrder([
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors('items.1.product_id');
    }

    public function test_customer_sees_only_own_orders(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $order = Order::factory()->for($owner)->create();
        Order::factory()->for($other)->create();

        Sanctum::actingAs($other);
        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = $this->customer();
        $order = Order::factory()->for($owner)->create();

        Sanctum::actingAs($this->customer());

        $this->getJson("/api/orders/{$order->id}")
            ->assertForbidden()
            ->assertExactJson(['status' => false, 'message' => 'This action is unauthorized.']);
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $order = Order::factory()->for($this->customer())->create();

        Sanctum::actingAs($this->customer());

        $this->postJson("/api/orders/{$order->id}/cancel")->assertForbidden();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_admin_can_view_any_order(): void
    {
        $order = Order::factory()->for($this->customer())->create();

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/orders/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_missing_order_returns_json_404(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/orders/999999')
            ->assertNotFound()
            ->assertExactJson(['status' => false, 'message' => 'Resource not found.']);
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $orderId = $this->placeOrder([['product_id' => $product->id, 'quantity' => 3]])->json('data.id');
        $this->assertSame(2, $product->fresh()->stock_quantity);

        $this->postJson("/api/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $product->fresh()->stock_quantity);

        $this->postJson("/api/orders/{$orderId}/cancel")->assertStatus(422);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_customer_cannot_cancel_paid_order(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->for($customer)->status(OrderStatus::PAID)->create();

        Sanctum::actingAs($customer);
        $this->postJson("/api/orders/{$order->id}/cancel")->assertStatus(422);
    }

    public function test_admin_can_move_order_through_statuses(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk()->assertJsonPath('data.status', 'shipped');

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'unknown'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_admin_cancelling_paid_order_restores_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);

        Sanctum::actingAs($this->customer());
        $orderId = $this->placeOrder([['product_id' => $product->id, 'quantity' => 4]])->json('data.id');

        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/admin/orders/{$orderId}/status", ['status' => 'paid'])->assertOk();
        $this->patchJson("/api/admin/orders/{$orderId}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_customer_cannot_access_admin_orders(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_status_enum_transitions(): void
    {
        $this->assertTrue(OrderStatus::PENDING->canTransitionTo(OrderStatus::PAID));
        $this->assertTrue(OrderStatus::PAID->canTransitionTo(OrderStatus::SHIPPED));
        $this->assertFalse(OrderStatus::SHIPPED->canTransitionTo(OrderStatus::CANCELLED));
        $this->assertFalse(OrderStatus::CANCELLED->canTransitionTo(OrderStatus::PAID));
        $this->assertFalse(OrderStatus::PENDING->canTransitionTo(OrderStatus::SHIPPED));
    }
}
