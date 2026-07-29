<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $this->authorize('viewAny', Order::class);

        $query = Order::withFullDetails()->latest('id');

        if ($user->isCliente()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isProveedor()) {
            $query->whereHas('items.product', function ($q) use ($user): void {
                $q->where('supplier_id', $user->id);
            });
        }

        $orders = $query->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);
        $order->load(['user', 'items.product.category', 'items.product.supplier']);

        return new OrderResource($order);
    }
}
