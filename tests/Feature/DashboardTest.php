<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $proveedor;
    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->proveedor = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'supplier_id' => $this->proveedor->id,
            'category_id' => $category->id,
            'price' => 200.00,
            'stock' => 3, // Low stock <= 5
        ]);

        $order = Order::create([
            'user_id' => $this->cliente->id,
            'status' => OrderStatus::Paid,
            'total_amount' => 400.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 200.00,
            'subtotal' => 400.00,
        ]);
    }

    public function test_admin_can_access_admin_dashboard_stats(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => ['total_revenue', 'total_orders_count', 'total_products_count', 'users_by_role'],
                'recent_orders',
                'top_products',
            ])
            ->assertJsonPath('stats.total_revenue', 400)
            ->assertJsonPath('stats.total_orders_count', 1);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $responseCliente = $this->actingAs($this->cliente, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');
        $responseCliente->assertStatus(403);

        $responseProveedor = $this->actingAs($this->proveedor, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');
        $responseProveedor->assertStatus(403);
    }

    public function test_proveedor_can_access_supplier_dashboard_stats(): void
    {
        $response = $this->actingAs($this->proveedor, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => ['total_supplier_revenue', 'my_products_count', 'low_stock_count'],
                'my_products',
                'recent_sales',
            ])
            ->assertJsonPath('stats.total_supplier_revenue', 400)
            ->assertJsonPath('stats.my_products_count', 1)
            ->assertJsonPath('stats.low_stock_count', 1);
    }

    public function test_cliente_cannot_access_supplier_dashboard(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard/stats');

        $response->assertStatus(403);
    }
}
