<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductType;
use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use App\Support\Money;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Number;

/**
 * Savat har bir (telegram_id, shop_id) juftligi uchun alohida saqlanadi.
 * Bitta foydalanuvchi bir nechta mijozga a'zo bo'lsa —
 * har mijozda o'z savatida ishlaydi.
 *
 * Cart kaliti: "{productId}:{productTypeId|0}". Bir mahsulot bir nechta tipda
 * buyurtma berilsa — har tip alohida CartItem sifatida saqlanadi.
 */
final class CartService
{
    private const TTL_SECONDS = 86_400;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly PromotionService $promotions,
    ) {}

    public function get(int $telegramId, int $shopId): Cart
    {
        $raw = $this->cache->get($this->key($telegramId, $shopId), []);

        return Cart::fromRaw(is_array($raw) ? $raw : []);
    }

    public function addItem(
        int $telegramId,
        int $shopId,
        Product $product,
        float $qty,
        ?int $packQty = null,
        ?ProductType $type = null,
    ): Cart {
        $cart = $this->get($telegramId, $shopId)->add($this->makeItem($product, $type, $qty, $packQty));

        return $this->persist($telegramId, $shopId, $cart);
    }

    public function addByPack(
        int $telegramId,
        int $shopId,
        Product $product,
        int $packQty,
        ?ProductType $type = null,
    ): Cart {
        $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));

        return $this->addItem($telegramId, $shopId, $product, $packQty * $packSize, $packQty, $type);
    }

    public function setItemQty(
        int $telegramId,
        int $shopId,
        Product $product,
        float $qty,
        ?int $packQty = null,
        ?ProductType $type = null,
    ): Cart {
        $cart = $this->get($telegramId, $shopId);

        if (! $cart->has($product->id, $type?->id)) {
            return $cart;
        }

        return $this->persist(
            $telegramId,
            $shopId,
            $cart->setQty($product->id, $qty, $packQty, $type?->id),
        );
    }

    public function setItemPackQty(
        int $telegramId,
        int $shopId,
        Product $product,
        int $packQty,
        ?ProductType $type = null,
    ): Cart {
        $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));

        return $this->setItemQty($telegramId, $shopId, $product, $packQty * $packSize, $packQty, $type);
    }

    public function removeItem(int $telegramId, int $shopId, int $productId, ?int $productTypeId = null): Cart
    {
        $cart = $this->get($telegramId, $shopId)->remove($productId, $productTypeId);

        return $this->persist($telegramId, $shopId, $cart);
    }

    public function clear(int $telegramId, int $shopId): void
    {
        $this->cache->forget($this->key($telegramId, $shopId));
    }

    /**
     * Savatni to'liq almashtirish (bitta Redis yozuv bilan).
     * Reorder kabi bulk operatsiyalar uchun ishlatiladi.
     *
     * @param  iterable<CartItem>  $items
     */
    public function replaceWith(int $telegramId, int $shopId, iterable $items): Cart
    {
        return $this->persist($telegramId, $shopId, new Cart($items));
    }

    public function formatSummary(Cart $cart): string
    {
        if ($cart->isEmpty()) {
            return 'Savat bo\'sh';
        }

        $lines = ['🛒 *Savat*', ''];

        foreach ($cart as $i => $item) {
            $title = $item->productTypeName !== null
                ? "{$item->productName} — {$item->productTypeName}"
                : $item->productName;

            $lines[] = sprintf(
                '%d. %s — %s (%s) = %s so\'m',
                $i + 1,
                $title,
                $this->formatQtyLabel($item),
                Number::format($item->price).' × '.$this->fmtQty($item->qty),
                Number::format($item->subtotal()),
            );
        }

        $lines[] = '';
        $lines[] = sprintf('*Jami: %s so\'m*', Number::format($cart->total()));

        return implode("\n", $lines);
    }

    public function makeItem(Product $product, ?ProductType $type, float $qty, ?int $packQty = null): CartItem
    {
        if ($type !== null) {
            $effectivePrice = $this->promotions->effectivePriceForType($product, $type);
            $packSize = max(1.0, (float) $type->pack_size);

            return new CartItem(
                productId: $product->id,
                productName: $product->name,
                price: $effectivePrice,
                qty: $qty,
                unit: $product->unit->value,
                packSize: $packSize,
                packQty: $packQty,
                bulkOnly: (bool) $type->bulk_only,
                productTypeId: $type->id,
                productTypeName: $type->name,
                productTypeCode: null,
                packPrice: Money::effectivePackPrice(
                    $type->pack_price !== null ? (float) $type->pack_price : null,
                    (float) $type->price,
                    $effectivePrice,
                    $packSize,
                ),
            );
        }

        $effectivePrice = $this->promotions->effectivePriceFor($product);
        $packSize = max(1.0, (float) $product->pack_size);

        return new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $effectivePrice,
            qty: $qty,
            unit: $product->unit->value,
            packSize: $packSize,
            packQty: $packQty,
            bulkOnly: (bool) $product->bulk_only,
            packPrice: Money::effectivePackPrice(
                $product->pack_price !== null ? (float) $product->pack_price : null,
                (float) $product->price,
                $effectivePrice,
                $packSize,
            ),
        );
    }

    private function formatQtyLabel(CartItem $item): string
    {
        $hasPacks = ($item->packQty ?? 0) > 0 && $item->packSize > 1;
        $loose = $item->looseQty();

        if ($hasPacks && $loose > 0) {
            return sprintf(
                '%d blok × %s %s + %s %s',
                $item->packQty,
                $this->fmtQty($item->packSize),
                $item->unit,
                $this->fmtQty($loose),
                $item->unit,
            );
        }

        if ($hasPacks) {
            return sprintf('%d blok × %s %s', $item->packQty, $this->fmtQty($item->packSize), $item->unit);
        }

        return sprintf('%s %s', $this->fmtQty($item->qty), $item->unit);
    }

    private function fmtQty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function persist(int $telegramId, int $shopId, Cart $cart): Cart
    {
        if ($cart->isEmpty()) {
            $this->clear($telegramId, $shopId);

            return $cart;
        }

        $this->cache->put($this->key($telegramId, $shopId), $cart->jsonSerialize(), self::TTL_SECONDS);

        return $cart;
    }

    private function key(int $telegramId, int $shopId): string
    {
        return "cart:tg:{$telegramId}:shop:{$shopId}";
    }
}
