<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopFavorite;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mijoz sevimlilari. Mijoz darajasida saqlanadi — ko'p a'zoli mijozda
 * barcha a'zolar bir ro'yxatni ko'rishadi. product IDs cache lanadi,
 * toggle vaqtida invalidate qilinadi.
 */
final class FavoriteService
{
    private const CACHE_TTL_SECONDS = 3_600;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Shu mijoz uchun sevimli mahsulot IDlari (cache lanadi).
     *
     * @return array<int, int>
     */
    public function productIds(int $shopId): array
    {
        return $this->cache->remember(
            $this->key($shopId),
            self::CACHE_TTL_SECONDS,
            fn (): array => ShopFavorite::query()
                ->forShop($shopId)
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    public function isFavorite(int $shopId, int $productId): bool
    {
        return in_array($productId, $this->productIds($shopId), true);
    }

    public function add(Shop $shop, Product $product): void
    {
        if ($product->dealer_id !== $shop->dealer_id) {
            return;
        }

        ShopFavorite::query()->firstOrCreate([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
        ]);

        $this->invalidate($shop->id);
    }

    public function remove(Shop $shop, int $productId): void
    {
        ShopFavorite::query()
            ->forShop($shop->id)
            ->where('product_id', $productId)
            ->delete();

        $this->invalidate($shop->id);
    }

    public function toggle(Shop $shop, Product $product): bool
    {
        if ($this->isFavorite($shop->id, $product->id)) {
            $this->remove($shop, $product->id);

            return false;
        }

        $this->add($shop, $product);

        return true;
    }

    /**
     * Product listiga `favorite` scope qo'shadi (mahsulot ro'yxatida filtr).
     */
    public function scopeFavoritesOnly(Builder $query, int $shopId): void
    {
        $query->whereIn('id', $this->productIds($shopId));
    }

    public function invalidate(int $shopId): void
    {
        $this->cache->forget($this->key($shopId));
    }

    private function key(int $shopId): string
    {
        return "shop:{$shopId}:fav_product_ids";
    }
}
