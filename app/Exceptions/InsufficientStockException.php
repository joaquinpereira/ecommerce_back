<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

final class InsufficientStockException extends Exception
{
    public function __construct(
        string $message = 'El producto no cuenta con suficiente stock disponible.',
        private readonly int $availableStock = 0,
        private readonly int $requestedQuantity = 0
    ) {
        parent::__construct($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'INSUFFICIENT_STOCK',
            'available_stock' => $this->availableStock,
            'requested_quantity' => $this->requestedQuantity,
        ], 422);
    }
}
