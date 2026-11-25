<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRulesTest extends TestCase
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

    public function test_list_endpoints_validate_through_form_requests(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/products?per_page=500')
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/orders?status=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/products?trashed=all')->assertStatus(422)->assertJsonValidationErrors('trashed');
        $this->getJson('/api/admin/users?role=owner')->assertStatus(422)->assertJsonValidationErrors('role');
        $this->getJson('/api/admin/orders?user_id=999999')->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_dashboard_stats_come_through_resource(): void
    {
        Sanctum::actingAs($this->admin());
        $this->customer();
        $this->customer();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonPath('data.stats.customers', 2)
            ->assertJsonPath('data.stats.admins', 1)
            ->assertJsonStructure(['status', 'message', 'data' => ['stats' => ['total', 'customers', 'admins', 'this_week']]]);
    }

    public function test_admin_cannot_delete_an_admin_account(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$otherAdmin->id}")
            ->assertForbidden()
            ->assertExactJson(['status' => false, 'message' => 'Admin accounts cannot be deleted from here.']);

        $this->assertModelExists($otherAdmin);
    }

    public function test_admin_cannot_delete_customer_with_orders(): void
    {
        Sanctum::actingAs($this->admin());
        $customer = $this->customer();
        Order::factory()->for($customer)->create();

        $this->deleteJson("/api/admin/users/{$customer->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Users with orders cannot be deleted.');

        $this->assertModelExists($customer);
    }

    public function test_admin_can_delete_customer_without_orders(): void
    {
        Sanctum::actingAs($this->admin());
        $customer = $this->customer();

        $this->deleteJson("/api/admin/users/{$customer->id}")->assertOk();

        $this->assertModelMissing($customer);
    }

    public function test_restoring_a_product_that_is_not_deleted_fails(): void
    {
        Sanctum::actingAs($this->admin());
        $product = Product::factory()->create();

        $this->postJson("/api/admin/products/{$product->id}/restore")
            ->assertStatus(422)
            ->assertExactJson(['status' => false, 'message' => 'Product is not deleted.']);
    }

    public function test_customer_cannot_view_inactive_product(): void
    {
        Sanctum::actingAs($this->customer());
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertNotFound()
            ->assertExactJson(['status' => false, 'message' => 'Product not found.']);
    }

    public function test_login_response_comes_through_resource(): void
    {
        $user = User::factory()->create(['email' => 'ravi@example.com']);

        $this->postJson('/api/login', ['email' => 'ravi@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token']]);
    }
}
