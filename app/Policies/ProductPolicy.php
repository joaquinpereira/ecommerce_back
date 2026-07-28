<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        if ($product->status === ProductStatus::Active) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->isAdmin() || ($user->isProveedor() && $product->supplier_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProveedor();
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProveedor() && $product->supplier_id === $user->id;
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProveedor() && $product->supplier_id === $user->id;
    }
}
