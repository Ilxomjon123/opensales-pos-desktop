<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductType extends Model
{
    /** @use HasFactory<ProductTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'pack_price',
        'cost_price',
        'pack_cost_price',
        'stock',
        'min_stock',
        'pack_size',
        'bulk_only',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'pack_price' => 'float',
            'cost_price' => 'float',
            'pack_cost_price' => 'float',
            'stock' => 'float',
            'min_stock' => 'float',
            'pack_size' => 'float',
            'bulk_only' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    public function packPrice(): float
    {
        if ($this->pack_price !== null) {
            return (float) $this->pack_price;
        }

        return round((float) $this->price * max(1.0, (float) $this->pack_size), 2);
    }

    public function stockInPacks(): int
    {
        return (int) floor((float) $this->stock / max(1.0, (float) $this->pack_size));
    }

    public function hasPack(): bool
    {
        return (float) $this->pack_size > 1;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }
}
