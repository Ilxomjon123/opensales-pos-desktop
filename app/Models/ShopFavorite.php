<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopFavoriteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShopFavorite extends Model
{
    /** @use HasFactory<ShopFavoriteFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'product_id',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }
}
