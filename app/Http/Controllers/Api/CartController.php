<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load(['items.product.category', 'items.product.supplier']);

        return response()->json([
            'cart' => new CartResource($cart),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($validated['product_id']);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        $newQuantity = ($existingItem?->quantity ?? 0) + $validated['quantity'];

        if ($product->stock < $newQuantity) {
            return response()->json([
                'message' => "Stock insuficiente. Solo hay {$product->stock} unidades disponibles.",
                'error' => 'INSUFFICIENT_STOCK',
                'available_stock' => $product->stock,
            ], 422);
        }

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $product->price,
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $product->price,
            ]);
        }

        $cart->load(['items.product.category', 'items.product.supplier']);

        return response()->json([
            'message' => 'Producto agregado al carrito exitosamente',
            'cart' => new CartResource($cart),
        ], 201);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->firstOrFail();

        $product = Product::findOrFail($item->product_id);

        if ($product->stock < $validated['quantity']) {
            return response()->json([
                'message' => "Stock insuficiente. Solo hay {$product->stock} unidades disponibles.",
                'error' => 'INSUFFICIENT_STOCK',
                'available_stock' => $product->stock,
            ], 422);
        }

        $item->update([
            'quantity' => $validated['quantity'],
            'unit_price' => $product->price,
        ]);

        $cart->load(['items.product.category', 'items.product.supplier']);

        return response()->json([
            'message' => 'Carrito actualizado exitosamente',
            'cart' => new CartResource($cart),
        ]);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        CartItem::where('cart_id', $cart->id)->where('id', $itemId)->delete();

        $cart->load(['items.product.category', 'items.product.supplier']);

        return response()->json([
            'message' => 'Ítem eliminado del carrito',
            'cart' => new CartResource($cart),
        ]);
    }

    public function clearCart(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'message' => 'Carrito vaciado exitosamente',
        ]);
    }
}
