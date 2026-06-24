<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\LowStockDetected;
use App\Models\Product;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Stok kam qolgan mahsulot haqida dillerga bildirishnoma yuborish.
 * Bir mahsulot 24 soat ichida faqat bir marta xabar beradi (rate-limit).
 */
final class StockAlertService
{
    private const COOLDOWN_SECONDS = 86_400;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Product ID bo'yicha tekshir va kerak bo'lsa event dispatch qil.
     * Eng so'nggi DB holatini o'qiydi — transaction tashqarisida chaqirilishi kerak.
     */
    public function checkAndNotifyByProductId(int $productId): void
    {
        $product = Product::query()->find($productId);

        if ($product !== null) {
            $this->checkAndNotify($product);
        }
    }

    /**
     * @param  iterable<int>  $productIds
     */
    public function checkAndNotifyMany(iterable $productIds): void
    {
        $ids = array_values(array_unique(array_map('intval', iterator_to_array((function () use ($productIds) {
            yield from $productIds;
        })(), false))));

        if ($ids === []) {
            return;
        }

        Product::query()->whereIn('id', $ids)->get()->each(
            fn (Product $p): ?bool => $this->checkAndNotify($p),
        );
    }

    public function checkAndNotify(Product $product): ?bool
    {
        if (! $product->isLowStock()) {
            return null;
        }

        $key = $this->cooldownKey($product->id);

        if ($this->cache->has($key)) {
            return false;
        }

        $this->cache->put($key, true, self::COOLDOWN_SECONDS);

        event(new LowStockDetected($product));

        return true;
    }

    public function resetCooldown(int $productId): void
    {
        $this->cache->forget($this->cooldownKey($productId));
    }

    private function cooldownKey(int $productId): string
    {
        return "low_stock_cooldown:{$productId}";
    }
}
