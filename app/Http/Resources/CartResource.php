<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');
        $totalAmount = 0.0;

        if ($items) {
            foreach ($items as $item) {
                $totalAmount += ($item->quantity * (float) $item->unit_price);
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($items),
            'total_items' => $items ? $items->sum('quantity') : 0,
            'total_amount' => round($totalAmount, 2),
        ];
    }
}
