<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
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

    public function test_admin_can_create_product(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Wireless Mouse',
            'sku' => 'acc-mouse-01',
            'price' => 799.50,
            'stock_quantity' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'ACC-MOUSE-01')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('products', ['sku' => 'ACC-MOUSE-01']);
    }

    public function test_sku_must_be_unique(): void
    {
        Sanctum::actingAs($this->admin());
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->postJson('/api/admin/products', [
            'name' => 'Another',
            'sku' => 'dup-1',
            'price' => 10,
            'stock_quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('sku');
    }

    public function test_admin_can_update_product(): void
    {
        Sanctum::actingAs($this->admin());
        $product = Product::factory()->create(['price' => 100]);

        $this->patchJson("/api/admin/products/{$product->id}", ['price' => 150, 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.price', '150.00')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_can_soft_delete_and_restore_product(): void
    {
        Sanctum::actingAs($this->admin());
        $product = Product::factory()->create();

        $this->deleteJson("/api/admin/products/{$product->id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->postJson("/api/admin/products/{$product->id}/restore")->assertOk();
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_customer_sees_only_active_products(): void
    {
        Sanctum::actingAs($this->customer());
        Product::factory()->create(['name' => 'Visible Item']);
        Product::factory()->inactive()->create(['name' => 'Hidden Item']);
        Product::factory()->create(['name' => 'Deleted Item'])->delete();

        $response = $this->getJson('/api/products')->assertOk();

        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('Visible Item'));
        $this->assertFalse($names->contains('Hidden Item'));
        $this->assertFalse($names->contains('Deleted Item'));
    }

    public function test_customer_can_search_by_name_or_sku(): void
    {
        Sanctum::actingAs($this->customer());
        Product::factory()->create(['name' => 'Laptop Stand', 'sku' => 'STAND-1']);
        Product::factory()->create(['name' => 'HDMI Cable', 'sku' => 'CBL-HDMI']);

        $this->getJson('/api/products?search=stand')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.sku', 'STAND-1');

        $this->getJson('/api/products?search=CBL')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'HDMI Cable');
    }

    public function test_customer_list_is_paginated(): void
    {
        Sanctum::actingAs($this->customer());
        Product::factory()->count(15)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.meta.total', 15)
            ->assertJsonPath('data.meta.last_page', 3);
    }

    public function test_customer_cannot_manage_products(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/admin/products', [])->assertForbidden();
    }

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }
}
