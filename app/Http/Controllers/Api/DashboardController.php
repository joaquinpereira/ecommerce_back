<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function adminStats(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Acceso no autorizado. Se requiere rol de Administrador.'], 403);
        }

        $totalRevenue = (float) Order::where('status', OrderStatus::Paid)->sum('total_amount');
        $totalOrdersCount = Order::where('status', OrderStatus::Paid)->count();
        $totalProductsCount = Product::count();

        $usersByRole = [
            'admin' => User::where('role', 'admin')->count(),
            'proveedor' => User::where('role', 'proveedor')->count(),
            'cliente' => User::where('role', 'cliente')->count(),
        ];

        $recentOrders = Order::withFullDetails()->latest('id')->take(10)->get();

        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid))
            ->with(['product.category', 'product.supplier'])
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_orders_count' => $totalOrdersCount,
                'total_products_count' => $totalProductsCount,
                'users_by_role' => $usersByRole,
            ],
            'recent_orders' => OrderResource::collection($recentOrders),
            'top_products' => $topProducts->map(fn ($item) => [
                'product' => new ProductResource($item->product),
                'total_sold' => (int) $item->total_sold,
                'total_revenue' => (float) $item->total_revenue,
            ]),
        ]);
    }

    public function supplierStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isProveedor() && ! $user->isAdmin()) {
            return response()->json(['message' => 'Acceso no autorizado. Se requiere rol de Proveedor.'], 403);
        }

        $supplierId = $user->id;

        $myProductsCount = Product::where('supplier_id', $supplierId)->count();
        $lowStockCount = Product::where('supplier_id', $supplierId)->where('stock', '<=', 5)->count();

        $totalSupplierRevenue = (float) OrderItem::whereHas('product', fn ($q) => $q->where('supplier_id', $supplierId))
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid))
            ->sum('subtotal');

        $myProducts = Product::where('supplier_id', $supplierId)->withRelations()->latest('id')->get();

        $recentSales = OrderItem::whereHas('product', fn ($q) => $q->where('supplier_id', $supplierId))
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid))
            ->with(['order.user', 'product'])
            ->latest('id')
            ->take(10)
            ->get();

        return response()->json([
            'stats' => [
                'total_supplier_revenue' => round($totalSupplierRevenue, 2),
                'my_products_count' => $myProductsCount,
                'low_stock_count' => $lowStockCount,
            ],
            'my_products' => ProductResource::collection($myProducts),
            'recent_sales' => $recentSales->map(fn ($item) => [
                'order_id' => $item->order_id,
                'client_name' => $item->order->user->name ?? 'Cliente',
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'date' => $item->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
