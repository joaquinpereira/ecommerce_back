<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\ProductCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret') ?? env('STRIPE_WEBHOOK_SECRET');

        $event = null;

        if ($webhookSecret && $sigHeader) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (SignatureVerificationException $e) {
                return response()->json(['error' => 'Firma de Webhook no válida: ' . $e->getMessage()], 400);
            } catch (\UnexpectedValueException $e) {
                return response()->json(['error' => 'Payload de Webhook no válido: ' . $e->getMessage()], 400);
            }
        } else {
            // Fallback for local testing or mock events
            $data = json_decode($payload, true);
            if (! isset($data['type'])) {
                return response()->json(['error' => 'Payload JSON inválido.'], 400);
            }
            $event = Event::constructFrom($data);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                return $this->handlePaymentIntentSucceeded($paymentIntent);

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                return $this->handlePaymentIntentFailed($paymentIntent);

            default:
                return response()->json(['message' => "Evento '{$event->type}' recibido sin acción."]);
        }
    }

    private function handlePaymentIntentSucceeded(object $paymentIntent): JsonResponse
    {
        $paymentIntentId = $paymentIntent->id ?? null;
        $orderId = $paymentIntent->metadata->order_id ?? null;

        /** @var Order|null $order */
        $order = Order::where('stripe_payment_intent_id', $paymentIntentId)
            ->orWhere('id', $orderId)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        // Idempotent processing with DB transaction & pessimistic locks
        DB::transaction(function () use ($order): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency check: if already marked paid, return safely without duplicating actions
            if ($lockedOrder->status === OrderStatus::Paid) {
                return;
            }

            // Deduct stock permanently for each product in order
            foreach ($lockedOrder->items as $item) {
                /** @var Product $product */
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $product->decrement('stock', $item->quantity);
            }

            // Update order status to Paid
            $lockedOrder->update([
                'status' => OrderStatus::Paid,
            ]);

            // Clear user's cart
            Cart::where('user_id', $lockedOrder->user_id)->delete();
        });

        // Invalidate Redis product cache after stock deductions
        $this->cacheService->invalidateProductCache();

        // Dispatch background job to send email notification
        SendOrderConfirmationEmailJob::dispatch($order);

        return response()->json([
            'message' => 'Webhook payment_intent.succeeded procesado exitosamente.',
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
    }

    private function handlePaymentIntentFailed(object $paymentIntent): JsonResponse
    {
        $paymentIntentId = $paymentIntent->id ?? null;
        $orderId = $paymentIntent->metadata->order_id ?? null;

        $order = Order::where('stripe_payment_intent_id', $paymentIntentId)
            ->orWhere('id', $orderId)
            ->first();

        if ($order) {
            $order->update(['status' => OrderStatus::Cancelled]);
        }

        return response()->json([
            'message' => 'Webhook payment_intent.payment_failed procesado.',
            'status' => 'cancelled',
        ]);
    }
}
