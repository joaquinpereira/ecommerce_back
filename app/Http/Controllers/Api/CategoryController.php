<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ProductCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $categories = $this->cacheService->rememberCategories(function () {
            return Category::active()->whereNull('parent_id')->withChildren()->get();
        });

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        $category->load(['parent', 'children']);

        return new CategoryResource($category);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = Category::create($validated);
        $this->cacheService->invalidateCategoryCache();

        return response()->json([
            'message' => 'Categoría creada exitosamente',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);
        $this->cacheService->invalidateCategoryCache();

        return response()->json([
            'message' => 'Categoría actualizada exitosamente',
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();
        $this->cacheService->invalidateCategoryCache();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente',
        ]);
    }
}
