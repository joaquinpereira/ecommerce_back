<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ProductCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_is_cached_and_invalidated_on_update(): void
    {
        Cache::flush();

        $proveedor = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'supplier_id' => $proveedor->id,
            'category_id' => $category->id,
            'name' => 'Audífonos Bluetooth',
            'price' => 49.99,
        ]);

        // First request populates cache
        $response1 = $this->getJson('/api/v1/products');
        $response1->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Audífonos Bluetooth');

        // Update product via API
        $updateResponse = $this->actingAs($proveedor, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Audífonos Bluetooth Pro',
            ]);

        $updateResponse->assertStatus(200);

        // Second request should reflect invalidated cache with new name
        $response2 = $this->getJson('/api/v1/products');
        $response2->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Audífonos Bluetooth Pro');
    }
}
