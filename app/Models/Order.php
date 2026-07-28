<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'total_amount',
        'stripe_payment_intent_id',
        'shipping_address',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order): void {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scope for full details eager loading to prevent N+1 queries
    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with(['user', 'items.product.supplier', 'items.product.category']);
    }
}
