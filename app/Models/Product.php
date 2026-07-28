<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'supplier_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'status' => ProductStatus::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product): void {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Eager Loading Scopes to eradicate N+1 problems
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['category', 'supplier']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }
}
