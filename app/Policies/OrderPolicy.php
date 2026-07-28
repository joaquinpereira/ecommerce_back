<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCliente() && $order->user_id === $user->id) {
            return true;
        }

        if ($user->isProveedor()) {
            // Proveedor can view if order contains at least one of supplier's products
            return $order->items()->whereHas('product', function ($query) use ($user): void {
                $query->where('supplier_id', $user->id);
            })->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isCliente() || $user->isAdmin();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
