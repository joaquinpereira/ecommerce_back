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
use Tests\TestCase;

final class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $proveedor1;
    private User $proveedor2;
    private User $cliente;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->proveedor1 = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $this->proveedor2 = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);

        $this->category = Category::create([
            'name' => 'Electrónica',
            'slug' => 'electronica',
            'description' => 'Categoría de prueba',
            'is_active' => true,
        ]);
    }

    public function test_product_policy_permissions(): void
    {
        $productProv1 = Product::create([
            'supplier_id' => $this->proveedor1->id,
            'category_id' => $this->category->id,
            'name' => 'Laptop Gamer',
            'slug' => 'laptop-gamer',
            'price' => 1200.00,
            'stock' => 10,
            'status' => ProductStatus::Active,
        ]);

        // Proveedor 1 can update own product
        $this->assertTrue($this->proveedor1->can('update', $productProv1));

        // Proveedor 2 cannot update Proveedor 1's product
        $this->assertFalse($this->proveedor2->can('update', $productProv1));

        // Cliente cannot create or update products
        $this->assertFalse($this->cliente->can('create', Product::class));
        $this->assertFalse($this->cliente->can('update', $productProv1));

        // Admin can update any product
        $this->assertTrue($this->admin->can('update', $productProv1));
    }

    public function test_order_policy_permissions(): void
    {
        $orderCliente = Order::create([
            'user_id' => $this->cliente->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 1200.00,
            'shipping_address' => ['city' => 'Quito'],
        ]);

        $productProv1 = Product::create([
            'supplier_id' => $this->proveedor1->id,
            'category_id' => $this->category->id,
            'name' => 'Mouse Pro',
            'price' => 50.00,
            'stock' => 20,
            'status' => ProductStatus::Active,
        ]);

        OrderItem::create([
            'order_id' => $orderCliente->id,
            'product_id' => $productProv1->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'subtotal' => 100.00,
        ]);

        // Cliente can view own order
        $this->assertTrue($this->cliente->can('view', $orderCliente));

        // Another client cannot view this order
        $otherCliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $this->assertFalse($otherCliente->can('view', $orderCliente));

        // Proveedor 1 can view order because it contains productProv1
        $this->assertTrue($this->proveedor1->can('view', $orderCliente));

        // Proveedor 2 cannot view order because it doesn't contain supplier 2 products
        $this->assertFalse($this->proveedor2->can('view', $orderCliente));

        // Admin can view any order
        $this->assertTrue($this->admin->can('view', $orderCliente));
    }

    public function test_category_policy_permissions(): void
    {
        $this->assertTrue($this->admin->can('create', Category::class));
        $this->assertFalse($this->proveedor1->can('create', Category::class));
        $this->assertFalse($this->cliente->can('create', Category::class));
    }
}
