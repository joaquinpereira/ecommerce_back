<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EagerLoadingNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loading_eradicates_n_plus_one_on_products(): void
    {
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);

        // Create 10 products
        for ($i = 1; $i <= 10; $i++) {
            Product::create([
                'supplier_id' => $supplier->id,
                'category_id' => $category->id,
                'name' => "Producto {$i}",
                'slug' => "producto-{$i}",
                'price' => 100.00 * $i,
                'stock' => 50,
                'status' => ProductStatus::Active,
            ]);
        }

        DB::enableQueryLog();

        // Query products using scopeWithRelations
        $products = Product::withRelations()->get();

        // Access relations on all 10 products
        foreach ($products as $product) {
            $this->assertNotNull($product->category->name);
            $this->assertNotNull($product->supplier->name);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Expected queries: 1 for products + 1 for categories + 1 for suppliers = 3 queries total (NOT 10+1 = 21 queries!)
        $this->assertCount(3, $queries, 'Eager loading scope should execute exactly 3 SQL queries for 10 products, eliminating N+1.');
    }

    public function test_eager_loading_eradicates_n_plus_one_on_orders(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::create(['name' => 'Calzado', 'slug' => 'calzado']);

        $product1 = Product::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Zapatillas Nike',
            'price' => 120.00,
            'stock' => 15,
            'status' => ProductStatus::Active,
        ]);

        $product2 = Product::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Zapatillas Adidas',
            'price' => 110.00,
            'stock' => 20,
            'status' => ProductStatus::Active,
        ]);

        $order = Order::create([
            'user_id' => $cliente->id,
            'status' => OrderStatus::Paid,
            'total_amount' => 230.00,
        ]);

        OrderItem::create(['order_id' => $order->id, 'product_id' => $product1->id, 'quantity' => 1, 'unit_price' => 120.00, 'subtotal' => 120.00]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product2->id, 'quantity' => 1, 'unit_price' => 110.00, 'subtotal' => 110.00]);

        DB::enableQueryLog();

        $orders = Order::withFullDetails()->get();

        foreach ($orders as $o) {
            $this->assertNotNull($o->user->name);
            foreach ($o->items as $item) {
                $this->assertNotNull($item->product->name);
                $this->assertNotNull($item->product->supplier->name);
            }
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Fixed constant number of queries for orders and all subrelations (1 orders + 1 users + 1 items + 1 products + 1 suppliers + 1 categories = 6)
        $this->assertLessThanOrEqual(6, count($queries), 'Order eager loading scope should execute constant subqueries.');
    }
}
