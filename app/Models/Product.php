<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'category_id',
        'name',
        'description',
        'price',
        'pack_price',
        'cost_price',
        'pack_cost_price',
        'stock',
        'min_stock',
        'pack_size',
        'bulk_only',
        'has_types',
        'unit',
        'is_active',
        'visibility',
        'sort_order',
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
            'has_types' => 'boolean',
            'unit' => ProductUnit::class,
            'is_active' => 'boolean',
            'visibility' => ProductVisibility::class,
            'sort_order' => 'integer',
        ];
    }

    public function isLowStock(): bool
    {
        if ($this->has_types) {
            return $this->relationLoaded('types')
                ? $this->types->contains(fn (ProductType $t): bool => $t->isLowStock())
                : false;
        }

        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock');
    }

    /**
     * Yig'indi qoldiq: types bo'lsa har type stock yig'indisi, bo'lmasa o'z stock.
     */
    public function totalStock(): float
    {
        if ($this->has_types) {
            return (float) ($this->relationLoaded('types')
                ? $this->types->sum('stock')
                : $this->types()->sum('stock'));
        }

        return (float) $this->stock;
    }

    /**
     * Boshlang'ich narx: types bo'lsa eng arzon faol type narxi, bo'lmasa o'z narxi.
     */
    public function startingPrice(): float
    {
        if ($this->has_types) {
            $types = $this->relationLoaded('types') ? $this->types : $this->types()->get();
            $active = $types->where('is_active', true);

            return (float) ($active->isEmpty() ? 0 : $active->min('price'));
        }

        return (float) $this->price;
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
        $size = max(1.0, (float) $this->pack_size);

        return (int) floor((float) $this->stock / $size);
    }

    public function hasPack(): bool
    {
        return (float) $this->pack_size > 1;
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->whereNull('product_type_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function allImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(ProductType::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeTypes(): HasMany
    {
        return $this->types()->where('is_active', true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }

    /**
     * Mahsulot katalogga chiqishi shartiga mos: types bo'lsa hech bo'lmaganda
     * bitta faol type stockda > 0 bo'lishi, aks holda o'z stockida > 0 bo'lishi kerak.
     */
    public function scopeAvailableInStock(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where(function (Builder $q): void {
                $q->where('has_types', false)->where('stock', '>', 0);
            })->orWhere(function (Builder $q): void {
                $q->where('has_types', true)->whereHas('types', function (Builder $qt): void {
                    $qt->where('is_active', true)->where('stock', '>', 0);
                });
            });
        });
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    /**
     * Botda (miniapp) ko'rinadigan mahsulotlar: bot_only yoki both.
     */
    public function scopeVisibleInBot(Builder $query): void
    {
        $query->whereIn('visibility', [
            ProductVisibility::BOT_ONLY->value,
            ProductVisibility::BOTH->value,
        ]);
    }

    /**
     * Birjada (marketplace) ko'rinadigan mahsulotlar: marketplace_only yoki both.
     */
    public function scopeVisibleInMarketplace(Builder $query): void
    {
        $query->whereIn('visibility', [
            ProductVisibility::MARKETPLACE_ONLY->value,
            ProductVisibility::BOTH->value,
        ]);
    }
}
