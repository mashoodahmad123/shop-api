<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopProductsReportTest extends TestCase
{
    use RefreshDatabase;

    private function sell(Product $product, int $quantity, OrderStatus $status = OrderStatus::PAID, string $date = '2026-11-10 12:00:00'): void
    {
        $order = Order::factory()->status($status)->create();
        $order->timestamps = false;
        $order->forceFill(['created_at' => $date])->save();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 100,
            'quantity' => $quantity,
            'line_total' => 100 * $quantity,
        ]);
    }

    private function report(array $query)
    {
        return $this->getJson('/api/admin/reports/top-products?' . http_build_query($query));
    }

    public function test_returns_top_five_products_by_quantity(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $products = Product::factory()->count(7)->create();
        foreach ($products as $index => $product) {
            $this->sell($product, $index + 1);
        }
        $this->sell($products[0], 20, OrderStatus::SHIPPED);

        $response = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertOk()
            ->assertJsonCount(5, 'data.products');

        $this->assertSame(
            [$products[0]->id, $products[6]->id, $products[5]->id, $products[4]->id, $products[3]->id],
            collect($response->json('data.products'))->pluck('product_id')->all()
        );
        $response->assertJsonPath('data.products.0.quantity_sold', 21)
            ->assertJsonPath('data.products.0.orders_count', 2)
            ->assertJsonPath('data.products.0.revenue', '2100.00');
    }

    public function test_respects_date_range_and_ignores_unsold_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $product = Product::factory()->create();

        $this->sell($product, 2, OrderStatus::PAID, '2026-11-10 00:00:00');
        $this->sell($product, 3, OrderStatus::PAID, '2026-11-12 23:59:59');
        $this->sell($product, 50, OrderStatus::PAID, '2026-11-13 00:00:01');
        $this->sell($product, 40, OrderStatus::CANCELLED, '2026-11-11 10:00:00');
        $this->sell($product, 30, OrderStatus::PENDING, '2026-11-11 10:00:00');

        $this->report(['from' => '2026-11-10', 'to' => '2026-11-12'])
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.quantity_sold', 5);
    }

    public function test_report_uses_a_single_query(): void
    {
        $products = Product::factory()->count(6)->create();
        foreach ($products as $product) {
            $this->sell($product, 2);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $rows = app(ReportService::class)->topProductsByQuantity('2026-11-01', '2026-11-30');
        $rows->each(fn ($row) => [$row->name, $row->sku, $row->quantity_sold]);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(5, $rows);
    }

    public function test_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->report([])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->report(['from' => '2026-11-10', 'to' => '2026-11-01'])
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_customer_cannot_view_report(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->assertForbidden();
    }
}
