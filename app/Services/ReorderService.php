<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use App\Support\Money;

/**
 * Oldingi buyurtmadan savatni qayta qurish.
 * Savat to'liq almashtiriladi; mahsulotlar joriy holatiga moslashtiriladi
 * (stok yetmasa kamaytiriladi, o'chirilganlar o'tkazib yuboriladi).
 */
final class ReorderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PromotionService $promotions,
    ) {}

    /**
     * @return array{cart: Cart, added: int, skipped: array<int, array{name: string, reason: string, requested?: int, added?: int}>}
     */
    public function execute(Shop $shop, Order $order, int $telegramId): array
    {
        $order->loadMissing('items');

        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $typeIds = $order->items
            ->pluck('product_type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('dealer_id', $shop->dealer_id)
            ->get()
            ->keyBy('id');

        $types = $typeIds === []
            ? collect()
            : ProductType::query()->whereIn('id', $typeIds)->get()->keyBy('id');

        $items = [];
        $skipped = [];

        foreach ($order->items as $orderItem) {
            $product = $products->get($orderItem->product_id);

            if ($product === null) {
                $skipped[] = ['name' => $orderItem->product_name, 'reason' => 'not_found'];

                continue;
            }

            if (! $product->is_active) {
                $skipped[] = ['name' => $product->name, 'reason' => 'deactivated'];

                continue;
            }

            $type = $orderItem->product_type_id !== null ? $types->get($orderItem->product_type_id) : null;
            $label = $orderItem->displayName();

            if ($orderItem->product_type_id !== null && ($type === null || ! $type->is_active)) {
                $skipped[] = ['name' => $label, 'reason' => 'deactivated'];

                continue;
            }

            $stock = $type !== null ? (float) $type->stock : (float) $product->stock;

            if ($stock <= 0) {
                $skipped[] = ['name' => $label, 'reason' => 'out_of_stock'];

                continue;
            }

            $requested = max(0.001, (float) $orderItem->qty);
            $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));

            $originalPackQty = $packSize > 1 ? max(0, (int) ($orderItem->pack_qty ?? 0)) : 0;
            $originalLooseQty = max(0.0, $requested - $originalPackQty * $packSize);

            $maxPacks = (int) floor($stock / $packSize);
            $finalPackQty = min($originalPackQty, $maxPacks);
            $remainingStock = $stock - $finalPackQty * $packSize;
            $finalLooseQty = min($originalLooseQty, max(0.0, $remainingStock));
            $finalQty = $finalPackQty * $packSize + $finalLooseQty;

            if ($finalQty <= 0) {
                $skipped[] = ['name' => $label, 'reason' => 'out_of_stock'];

                continue;
            }

            $price = $type !== null
                ? $this->promotions->effectivePriceForType($product, $type)
                : $this->promotions->effectivePriceFor($product);
            $basePrice = $type !== null ? (float) $type->price : (float) $product->price;
            $basePackPrice = $type?->pack_price ?? $product->pack_price;
            $packPrice = Money::effectivePackPrice(
                $basePackPrice !== null ? (float) $basePackPrice : null,
                $basePrice,
                $price,
                $packSize,
            );

            $items[] = new CartItem(
                productId: $product->id,
                productName: $product->name,
                price: $price,
                qty: $finalQty,
                unit: $product->unit->value,
                packSize: $packSize,
                packQty: $finalPackQty > 0 ? $finalPackQty : null,
                bulkOnly: (bool) ($type?->bulk_only ?? $product->bulk_only),
                productTypeId: $type?->id,
                productTypeName: $type?->name,
                productTypeCode: null,
                packPrice: $packPrice,
            );

            if ($finalQty < $requested) {
                $skipped[] = [
                    'name' => $label,
                    'reason' => 'partial_stock',
                    'requested' => $requested,
                    'added' => $finalQty,
                ];
            }
        }

        $cart = $this->cartService->replaceWith($telegramId, $shop->id, $items);

        return [
            'cart' => $cart,
            'added' => count($items),
            'skipped' => $skipped,
        ];
    }
}
