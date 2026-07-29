<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class ProductCacheService
{
    private const TTL_SECONDS = 3600; // 1 hour

    /**
     * Check if cache tags are supported by the current driver.
     */
    private function supportsTags(): bool
    {
        return Cache::supportsTags();
    }

    public function rememberProductsPage(int $page, ?int $categoryId, ?string $search, Closure $callback): mixed
    {
        $key = "products:page:{$page}:cat:" . ($categoryId ?? 'all') . ':q:' . md5($search ?? '');

        if ($this->supportsTags()) {
            return Cache::tags(['products', 'catalog'])->remember($key, self::TTL_SECONDS, $callback);
        }

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public function rememberCategories(Closure $callback): mixed
    {
        $key = 'categories:active:all';

        if ($this->supportsTags()) {
            return Cache::tags(['categories'])->remember($key, self::TTL_SECONDS, $callback);
        }

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public function invalidateProductCache(?int $productId = null): void
    {
        if ($this->supportsTags()) {
            Cache::tags(['products', 'catalog'])->flush();
        } else {
            Cache::flush();
        }
    }

    public function invalidateCategoryCache(): void
    {
        if ($this->supportsTags()) {
            Cache::tags(['categories'])->flush();
        } else {
            Cache::flush();
        }
    }
}
