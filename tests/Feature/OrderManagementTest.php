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

final class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente1;
    private User $cliente2;
    private User $proveedor1;
    private User $admin;
    private Order $order1;
    private Order $order2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->cliente1 = User::factory()->create(['role' => UserRole::Cliente->value]);
        $this->cliente2 = User::factory()->create(['role' => UserRole::Cliente->value]);
        $this->proveedor1 = User::factory()->create(['role' => UserRole::Proveedor->value]);

        $category = Category::factory()->create();

        $productProv1 = Product::factory()->create([
            'supplier_id' => $this->proveedor1->id,
            'category_id' => $category->id,
            'price' => 100.00,
        ]);

        // Order 1 for Cliente 1 with product from Proveedor 1
        $this->order1 = Order::create([
            'user_id' => $this->cliente1->id,
            'status' => OrderStatus::Paid,
            'total_amount' => 100.00,
        ]);

        OrderItem::create([
            'order_id' => $this->order1->id,
            'product_id' => $productProv1->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        // Order 2 for Cliente 2
        $this->order2 = Order::create([
            'user_id' => $this->cliente2->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 200.00,
        ]);
    }

    public function test_cliente_can_list_only_own_orders(): void
    {
        $response = $this->actingAs($this->cliente1, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->order1->id);
    }

    public function test_proveedor_can_list_orders_containing_their_products(): void
    {
        $response = $this->actingAs($this->proveedor1, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->order1->id);
    }

    public function test_admin_can_list_all_orders(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
