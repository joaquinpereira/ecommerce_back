<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Silla Ergonómica Pro',
            'price' => 250.00,
            'stock' => 10,
        ]);
    }

    public function test_user_can_add_item_to_cart(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('cart.total_items', 2)
            ->assertJsonPath('cart.total_amount', 500);
    }

    public function test_cannot_add_more_than_available_stock_to_cart(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 20, // Stock is 10
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_STOCK');
    }

    public function test_user_can_update_cart_item_quantity(): void
    {
        // Add 2 items first
        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $cartResponse = $this->actingAs($this->cliente, 'sanctum')->getJson('/api/v1/cart');
        $itemId = $cartResponse->json('cart.items.0.id');

        // Update quantity to 4
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->putJson("/api/v1/cart/items/{$itemId}", [
                'quantity' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('cart.total_items', 4)
            ->assertJsonPath('cart.total_amount', 1000);
    }

    public function test_user_can_remove_cart_item(): void
    {
        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $cartResponse = $this->actingAs($this->cliente, 'sanctum')->getJson('/api/v1/cart');
        $itemId = $cartResponse->json('cart.items.0.id');

        $response = $this->actingAs($this->cliente, 'sanctum')
            ->deleteJson("/api/v1/cart/items/{$itemId}");

        $response->assertStatus(200)
            ->assertJsonPath('cart.total_items', 0);
    }
}
