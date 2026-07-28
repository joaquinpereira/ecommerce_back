<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => $this->status->value,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'supplier' => new UserResource($this->whenLoaded('supplier')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
