<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PromotionScope;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Promotion;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

/**
 * Mahsulot uchun joriy samarali narx.
 * Dillerning faol aksiyalari 5 daqiqa cache lanadi (write bo'lganda invalidate).
 *
 * Qoidalar:
 *   - Bir vaqtning o'zida bir nechta aksiya mos kelsa — eng yuqori chegirmani qo'llaymiz
 *   - Per-product > per-category > all (aniqroq ustuvor) — lekin faqat foiz jihatidan eng katta g'olib
 */
final class PromotionService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * @return Collection<int, Promotion>
     */
    public function activeFor(int $dealerId): Collection
    {
        // Xom arraylar cache ga yoziladi — Eloquent instanslarini to'g'ridan-to'g'ri cache qilish
        // ba'zi serializerlar (Redis/igbinary, opcache) da "__PHP_Incomplete_Class" xatoligiga olib keladi.
        // Xom DB qiymatlari keyin `Promotion::hydrate()` orqali qayta tiklanadi va castlar qo'llaniladi.
        $rows = $this->cache->remember(
            $this->key($dealerId),
            self::CACHE_TTL_SECONDS,
            fn (): array => Promotion::query()
                ->forDealer($dealerId)
                ->active()
                ->orderByDesc('discount_percent')
                ->get()
                ->map(fn (Promotion $p): array => $p->getAttributes())
                ->all(),
        );

        return Promotion::hydrate(is_array($rows) ? $rows : []);
    }

    public function discountPercentFor(Product $product): int
    {
        $promo = $this->resolveFor($product);

        return $promo?->discount_percent ?? 0;
    }

    public function effectivePriceFor(Product $product): float
    {
        $percent = $this->discountPercentFor($product);

        if ($percent <= 0) {
            return (float) $product->price;
        }

        return round((float) $product->price * (100 - $percent) / 100, 6);
    }

    /**
     * Type narxiga aksiyani qo'llash. Aksiya product darajasida tekshiriladi
     * (type ham mahsulotning bir qismi). Aksiya yo'q bo'lsa — type narxi qaytariladi.
     */
    public function effectivePriceForType(Product $product, ProductType $type): float
    {
        $percent = $this->discountPercentFor($product);

        if ($percent <= 0) {
            return (float) $type->price;
        }

        return round((float) $type->price * (100 - $percent) / 100, 6);
    }

    public function resolveFor(Product $product): ?Promotion
    {
        $promos = $this->activeFor($product->dealer_id);

        return $promos
            ->filter(fn (Promotion $p): bool => $this->matches($p, $product))
            ->sortByDesc('discount_percent')
            ->first();
    }

    public function invalidate(int $dealerId): void
    {
        $this->cache->forget($this->key($dealerId));
    }

    private function matches(Promotion $promo, Product $product): bool
    {
        return match ($promo->scope) {
            PromotionScope::ALL => true,
            PromotionScope::PRODUCT => $promo->target_id === $product->id,
            PromotionScope::CATEGORY => $product->category_id !== null
                && $promo->target_id === $product->category_id,
        };
    }

    private function key(int $dealerId): string
    {
        return "dealer:{$dealerId}:promotions:active";
    }
}
