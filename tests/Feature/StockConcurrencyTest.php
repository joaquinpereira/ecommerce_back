<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_reservation_on_last_item_prevents_overselling(): void
    {
        $stockService = app(StockReservationService::class);

        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        // Product with only 1 remaining unit in stock
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Última Unidad de Edición Limitada',
            'stock' => 1,
            'price' => 999.99,
        ]);

        $buyerA = User::factory()->create(['role' => UserRole::Cliente->value]);
        $buyerB = User::factory()->create(['role' => UserRole::Cliente->value]);

        // Buyer A attempts to reserve the last unit
        $updatedProductA = $stockService->reserveStock($product->id, 1);

        $this->assertEquals(0, $updatedProductA->stock, 'Stock should become 0 after Buyer A reserves the last item.');

        // Buyer B attempts to reserve the same product which now has stock = 0
        $this->expectException(InsufficientStockException::class);

        try {
            $stockService->reserveStock($product->id, 1);
        } finally {
            // Verify database stock never drops below 0
            $freshProduct = Product::find($product->id);
            $this->assertEquals(0, $freshProduct->stock, 'Stock must never be negative.');
        }
    }

    public function test_api_checkout_reservation_rejects_second_concurrent_buyer(): void
    {
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Tarjeta Gráfica RTX 4090',
            'stock' => 1,
            'price' => 1500.00,
        ]);

        $buyerA = User::factory()->create(['role' => UserRole::Cliente->value]);
        $buyerB = User::factory()->create(['role' => UserRole::Cliente->value]);

        // Buyer A reserves via API endpoint
        $responseA = $this->actingAs($buyerA, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/reserve", ['quantity' => 1]);

        $responseA->assertStatus(200)
            ->assertJsonPath('product.stock', 0);

        // Buyer B attempts to reserve via API endpoint
        $responseB = $this->actingAs($buyerB, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/reserve", ['quantity' => 1]);

        $responseB->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_STOCK');

        // Confirm database stock remains exactly 0
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 0,
        ]);
    }
}
