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

final class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private StockReservationService $stockService;
    private Product $product;
    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockReservationService::class);

        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();
        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);

        $this->product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Consola PlayStation 5',
            'stock' => 5,
            'price' => 500.00,
        ]);
    }

    public function test_reserve_stock_reduces_product_stock_atomically(): void
    {
        $updatedProduct = $this->stockService->reserveStock($this->product->id, 2);

        $this->assertEquals(3, $updatedProduct->stock);
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 3,
        ]);
    }

    public function test_reserve_stock_throws_insufficient_stock_exception_when_over_requested(): void
    {
        $this->expectException(InsufficientStockException::class);

        $this->stockService->reserveStock($this->product->id, 10);

        // Verify stock remains untouched at 5 in database
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 5,
        ]);
    }

    public function test_release_stock_restores_product_stock(): void
    {
        $this->stockService->reserveStock($this->product->id, 2); // stock becomes 3
        $updatedProduct = $this->stockService->releaseStock($this->product->id, 2); // stock restored to 5

        $this->assertEquals(5, $updatedProduct->stock);
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 5,
        ]);
    }

    public function test_reserve_stock_via_api_endpoint(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/v1/products/{$this->product->id}/reserve", [
                'quantity' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('product.stock', 3);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 3,
        ]);
    }

    public function test_reserve_stock_via_api_endpoint_fails_when_overselling(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/v1/products/{$this->product->id}/reserve", [
                'quantity' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('available_stock', 5);
    }
}
