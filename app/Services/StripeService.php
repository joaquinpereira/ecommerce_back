<?php

declare(strict_types=1);

namespace App\Services;

use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secretKey = config('stripe.secret') ?? 'sk_test_placeholder';
        $this->stripe = new StripeClient($secretKey);
    }

    /**
     * Create a Stripe PaymentIntent for the given amount in cents.
     */
    public function createPaymentIntent(int $amountCents, string $currency = 'usd', array $metadata = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => $metadata,
        ]);
    }
}
