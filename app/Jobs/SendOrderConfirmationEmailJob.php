<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Order $order
    ) {}

    public function handle(): void
    {
        $this->order->load(['user', 'items.product']);

        if ($this->order->user && $this->order->user->email) {
            Mail::to($this->order->user->email)->send(new OrderConfirmationMail($this->order));
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('Error enviando correo de confirmación de orden', [
            'order_id' => $this->order->id,
            'error' => $e->getMessage(),
        ]);
    }
}
