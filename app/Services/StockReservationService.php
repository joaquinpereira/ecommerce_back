<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class StockReservationService
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /**
     * Reserve stock for a product using pessimistic locking (lockForUpdate)
     * within an atomic database transaction.
     *
     * @throws InsufficientStockException
     */
    public function reserveStock(int $productId, int $quantity): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad a reservar debe ser mayor a cero.');
        }

        $product = DB::transaction(function () use ($productId, $quantity): Product {
            /** @var Product $product */
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock < $quantity) {
                throw new InsufficientStockException(
                    message: "Stock insuficiente para el producto '{$product->name}'. Disponible: {$product->stock}, Solicitado: {$quantity}.",
                    availableStock: $product->stock,
                    requestedQuantity: $quantity
                );
            }

            $product->decrement('stock', $quantity);
            $product->refresh();

            return $product;
        });

        // Invalidate Redis product cache after stock change
        $this->cacheService->invalidateProductCache($productId);

        return $product;
    }

    /**
     * Release (restore) stock for a product atomically using pessimistic locking.
     */
    public function releaseStock(int $productId, int $quantity): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad a liberar debe ser mayor a cero.');
        }

        $product = DB::transaction(function () use ($productId, $quantity): Product {
            /** @var Product $product */
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $product->increment('stock', $quantity);
            $product->refresh();

            return $product;
        });

        $this->cacheService->invalidateProductCache($productId);

        return $product;
    }
}
