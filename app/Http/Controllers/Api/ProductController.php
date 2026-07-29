<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ReserveStockRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductCacheService;
use App\Services\StockReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $page = (int) $request->input('page', 1);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $perPage = (int) $request->input('per_page', 15);

        // Fetch products using Cache Tags & Eager Loading scope ( eradicating N+1 queries )
        $products = $this->cacheService->rememberProductsPage($page, $categoryId, $search, function () use ($categoryId, $search, $perPage) {
            $query = Product::active()->withRelations();

            if ($categoryId !== null) {
                $query->where('category_id', $categoryId);
            }

            if ($search !== null) {
                $query->where('name', 'like', "%{$search}%");
            }

            return $query->latest('id')->paginate($perPage);
        });

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'supplier']);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $supplierId = $request->user()->isProveedor()
            ? $request->user()->id
            : ($request->input('supplier_id') ?? $request->user()->id);

        $product = Product::create([
            'supplier_id' => $supplierId,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->cacheService->invalidateProductCache();
        $product->load(['category', 'supplier']);

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'product' => new ProductResource($product),
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        $this->cacheService->invalidateProductCache($product->id);
        $product->load(['category', 'supplier']);

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();
        $this->cacheService->invalidateProductCache($product->id);

        return response()->json([
            'message' => 'Producto eliminado exitosamente',
        ]);
    }

    public function reserve(ReserveStockRequest $request, Product $product, StockReservationService $reservationService): JsonResponse
    {
        $quantity = (int) $request->validated('quantity');

        $updatedProduct = $reservationService->reserveStock($product->id, $quantity);
        $updatedProduct->load(['category', 'supplier']);

        return response()->json([
            'message' => "Se han reservado exitosamente {$quantity} unidades del producto.",
            'product' => new ProductResource($updatedProduct),
        ]);
    }
}
