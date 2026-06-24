<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ProductPriceChanged;
use App\Models\Product;

/**
 * Mahsulot narxi o'zgarganda ProductPriceChanged event dispatch qiladi.
 * Diller bayrog'i (notify_on_price_change) tekshiruvi listenerda — toggle
 * navbat kutilayotgan paytda o'zgarsa ham to'g'ri ishlashi uchun.
 */
final class PriceChangeNotifier
{
    private const EPSILON = 0.000001;

    public function dispatchIfChanged(Product $product, float $oldPrice, ?float $oldPackPrice): void
    {
        $newPrice = (float) $product->price;
        $newPackPrice = $product->pack_price !== null ? (float) $product->pack_price : null;

        if ($this->same($oldPrice, $newPrice) && $this->packSame($oldPackPrice, $newPackPrice)) {
            return;
        }

        event(new ProductPriceChanged(
            product: $product,
            oldPrice: $oldPrice,
            oldPackPrice: $oldPackPrice,
            newPrice: $newPrice,
            newPackPrice: $newPackPrice,
        ));
    }

    private function same(float $a, float $b): bool
    {
        return abs($a - $b) < self::EPSILON;
    }

    private function packSame(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $this->same($a, $b);
    }
}
