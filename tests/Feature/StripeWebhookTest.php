<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private Product $product;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Laptop Dell XPS 15',
            'price' => 1500.00,
            'stock' => 10,
        ]);

        $this->order = Order::create([
            'user_id' => $this->cliente->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 3000.00,
            'stripe_payment_intent_id' => 'pi_test_stripe_webhook_123',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 1500.00,
            'subtotal' => 3000.00,
        ]);

        // Cart items for user
        $cart = Cart::create(['user_id' => $this->cliente->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 1500.00,
        ]);
    }

    public function test_stripe_webhook_payment_intent_succeeded_updates_order_and_stock(): void
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_stripe_webhook_123',
                    'metadata' => [
                        'order_id' => (string) $this->order->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/stripe', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'paid');

        // Check order updated to paid
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'paid',
        ]);

        // Check stock permanently decremented from 10 to 8
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 8,
        ]);

        // Check user's cart cleared
        $this->assertDatabaseMissing('carts', [
            'user_id' => $this->cliente->id,
        ]);

        // Check queue email job pushed
        Queue::assertPushed(SendOrderConfirmationEmailJob::class, function ($job) {
            return $job->order->id === $this->order->id;
        });
    }

    public function test_stripe_webhook_idempotency_prevents_duplicate_stock_deduction(): void
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_stripe_webhook_123',
                    'metadata' => [
                        'order_id' => (string) $this->order->id,
                    ],
                ],
            ],
        ];

        // First call
        $this->postJson('/api/v1/webhooks/stripe', $payload);

        // Second duplicate call (Stripe retry simulation)
        $response2 = $this->postJson('/api/v1/webhooks/stripe', $payload);
        $response2->assertStatus(200);

        // Verify stock is 8 and NOT 6 (no double deduction)
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 8,
        ]);
    }

    public function test_stripe_webhook_payment_intent_failed_cancels_order(): void
    {
        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_stripe_webhook_123',
                    'metadata' => [
                        'order_id' => (string) $this->order->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/stripe', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'cancelled',
        ]);
    }
}
