<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;

final class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = User::factory()->create(['role' => UserRole::Cliente->value]);
        $supplier = User::factory()->create(['role' => UserRole::Proveedor->value]);
        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Monitor 4K OLED',
            'price' => 799.99,
            'stock' => 5,
        ]);
    }

    public function test_cannot_checkout_empty_cart(): void
    {
        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/checkout/create-payment-intent');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'El carrito está vacío. Agregue productos antes de realizar el checkout.');
    }

    public function test_creates_order_and_stripe_payment_intent_successfully(): void
    {
        // Add item to cart
        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Mock StripeService PaymentIntent response
        $mockPaymentIntent = new PaymentIntent('pi_mock_12345');
        $mockPaymentIntent->client_secret = 'pi_mock_12345_secret_abc123';

        $this->mock(StripeService::class, function (MockInterface $mock) use ($mockPaymentIntent): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(159998, 'usd', \Mockery::any())
                ->andReturn($mockPaymentIntent);
        });

        $response = $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/v1/checkout/create-payment-intent', [
                'shipping_address' => [
                    'street' => 'Av. Principal 123',
                    'city' => 'Quito',
                    'country' => 'Ecuador',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['client_secret', 'order_id', 'order_uuid', 'total_amount'])
            ->assertJsonPath('client_secret', 'pi_mock_12345_secret_abc123')
            ->assertJsonPath('total_amount', 1599.98);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->cliente->id,
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_mock_12345',
            'total_amount' => 1599.98,
        ]);
    }
}
