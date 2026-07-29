<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CheckoutController extends Controller
{
    public function createPaymentIntent(Request $request, StripeService $stripeService): JsonResponse
    {
        $user = $request->user();

        $cart = Cart::where('user_id', $user->id)
            ->with(['items.product'])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'El carrito está vacío. Agregue productos antes de realizar el checkout.',
            ], 400);
        }

        $validatedAddress = $request->validate([
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.street' => ['nullable', 'string'],
            'shipping_address.city' => ['nullable', 'string'],
            'shipping_address.country' => ['nullable', 'string'],
        ]);

        // Transaction with pessimistic locking to verify stock and compute total securely
        /** @var Order $order */
        $order = DB::transaction(function () use ($user, $cart, $validatedAddress): Order {
            $totalAmount = 0.0;
            $itemsData = [];

            foreach ($cart->items as $item) {
                /** @var Product $product */
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock < $item->quantity) {
                    throw new \RuntimeException("El producto '{$product->name}' no tiene suficiente stock disponible. Quedan {$product->stock} unidades.");
                }

                $subtotal = $item->quantity * (float) $product->price;
                $totalAmount += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            // Create Order record in pending status
            $order = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'total_amount' => round($totalAmount, 2),
                'shipping_address' => $validatedAddress['shipping_address'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $order;
        });

        // Amount in cents for Stripe PaymentIntent
        $amountCents = (int) round($order->total_amount * 100);

        try {
            $paymentIntent = $stripeService->createPaymentIntent(
                amountCents: $amountCents,
                currency: 'usd',
                metadata: [
                    'order_id' => (string) $order->id,
                    'order_uuid' => $order->uuid,
                    'user_id' => (string) $user->id,
                    'user_email' => $user->email,
                ]
            );

            $order->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'total_amount' => (float) $order->total_amount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al inicializar la pasarela de pago Stripe: ' . $e->getMessage(),
            ], 500);
        }
    }
}
