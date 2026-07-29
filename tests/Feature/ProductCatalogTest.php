<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_paginated_active_products(): void
    {
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        Product::factory()->count(20)->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/products?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'name', 'slug', 'price', 'stock', 'status', 'category', 'supplier'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(15, 'data');
    }

    public function test_can_filter_products_by_category(): void
    {
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $cat1 = Category::factory()->create(['name' => 'Computadoras']);
        $cat2 = Category::factory()->create(['name' => 'Monitores']);

        Product::factory()->count(3)->create(['category_id' => $cat1->id, 'supplier_id' => $supplier->id]);
        Product::factory()->count(2)->create(['category_id' => $cat2->id, 'supplier_id' => $supplier->id]);

        $response = $this->getJson("/api/v1/products?category_id={$cat1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_search_products_by_name(): void
    {
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        Product::factory()->create(['name' => 'MacBook Pro 16', 'supplier_id' => $supplier->id, 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Mouse Inalámbrico', 'supplier_id' => $supplier->id, 'category_id' => $category->id]);

        $response = $this->getJson('/api/v1/products?search=MacBook');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'MacBook Pro 16');
    }

    public function test_proveedor_can_create_product(): void
    {
        $proveedor = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $response = $this->actingAs($proveedor, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Teclado Mecánico RGB',
                'description' => 'Teclado con switches red',
                'price' => 89.99,
                'stock' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('product.name', 'Teclado Mecánico RGB')
            ->assertJsonPath('product.supplier.id', $proveedor->id);

        $this->assertDatabaseHas('products', [
            'name' => 'Teclado Mecánico RGB',
            'supplier_id' => $proveedor->id,
        ]);
    }

    public function test_cliente_cannot_create_product(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $category = Category::factory()->create();

        $response = $this->actingAs($cliente, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Producto No Permitido',
                'price' => 10.00,
                'stock' => 5,
            ]);

        $response->assertStatus(403);
    }
}
