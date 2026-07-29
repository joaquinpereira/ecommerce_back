<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;

final class RealUserFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_end_to_end_user_purchasing_flow(): void
    {
        Queue::fake();

        // 1. Seed initial supplier & catalog product
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create(['name' => 'Laptops']);

        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'MacBook Air M3',
            'price' => 1200.00,
            'stock' => 5,
        ]);

        // 2. Step 1: User Registers as Cliente
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Carlos Cliente',
            'email' => 'carlos.cliente@example.com',
            'password' => 'SecurePass123!',
            'role' => 'cliente',
        ]);

        $regResponse->assertStatus(201);
        $token = $regResponse->json('access_token');
        $this->assertNotEmpty($token);

        // 3. Step 2: User logs in
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'carlos.cliente@example.com',
            'password' => 'SecurePass123!',
        ]);

        $loginResponse->assertStatus(200);

        // 4. Step 3: Browse catalog
        $catalogResponse = $this->getJson('/api/v1/products');
        $catalogResponse->assertStatus(200)
            ->assertJsonPath('data.0.name', 'MacBook Air M3');

        // 5. Step 4: Add product to cart
        $cartAddResponse = $this->withToken($token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $cartAddResponse->assertStatus(201)
            ->assertJsonPath('cart.total_items', 2)
            ->assertJsonPath('cart.total_amount', 2400);

        // 6. Step 5: Checkout & create Stripe PaymentIntent (Mocked)
        $mockPaymentIntent = new PaymentIntent('pi_e2e_real_flow_999');
        $mockPaymentIntent->client_secret = 'pi_e2e_real_flow_999_secret_xyz';

        $this->mock(StripeService::class, function (MockInterface $mock) use ($mockPaymentIntent): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn($mockPaymentIntent);
        });

        $checkoutResponse = $this->withToken($token)
            ->postJson('/api/v1/checkout/create-payment-intent', [
                'shipping_address' => [
                    'street' => 'Calle 10 de Agosto 456',
                    'city' => 'Quito',
                    'country' => 'Ecuador',
                ],
            ]);

        $checkoutResponse->assertStatus(200)
            ->assertJsonPath('client_secret', 'pi_e2e_real_flow_999_secret_xyz')
            ->assertJsonPath('total_amount', 2400);

        $orderId = $checkoutResponse->json('order_id');

        // 7. Step 6: Simulate Stripe Webhook notification (payment_intent.succeeded)
        $webhookPayload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_e2e_real_flow_999',
                    'metadata' => [
                        'order_id' => (string) $orderId,
                    ],
                ],
            ],
        ];

        $webhookResponse = $this->postJson('/api/v1/webhooks/stripe', $webhookPayload);
        $webhookResponse->assertStatus(200)
            ->assertJsonPath('status', 'paid');

        // 8. Final Assertions:
        // A. Order is marked as paid
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_e2e_real_flow_999',
        ]);

        // B. Product stock permanently decremented from 5 to 3
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);

        // C. Cart cleared
        $this->assertDatabaseMissing('carts', [
            'user_id' => $loginResponse->json('user.id'),
        ]);

        // D. Email notification job queued
        Queue::assertPushed(SendOrderConfirmationEmailJob::class);
    }
}
