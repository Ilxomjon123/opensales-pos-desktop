<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Events\OrderCreated;
use App\Events\OrderDeliverymanChanged;
use App\Events\OrderEdited;
use App\Events\OrderStatusChanged;
use App\Exceptions\Domain\BelowMinOrderAmountException;
use App\Exceptions\Domain\EmptyCartException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidOrderTransitionException;
use App\Exceptions\Domain\OrderAssignmentException;
use App\Exceptions\Domain\OutsideDeliveryZoneException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Dto\Cart;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly FinanceService $financeService,
        private readonly StockAlertService $stockAlert,
        private readonly DeliveryZoneService $deliveryZones,
    ) {}

    /**
     * Sotuv paytida tannarx snapshot — keyin product.cost_price o'zgarsa ham
     * eski order_items'dagi unit_cost o'zgarmaydi. Profit Report shu snapshot
     * orqali tarixiy aniqlikni saqlaydi. ProductType bo'lsa uning tannarxi,
     * yo'q bo'lsa product darajasidagi tannarx olinadi.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function costSnapshot(?Product $product, ?ProductType $type): array
    {
        $unitCost = $type?->cost_price ?? $product?->cost_price;
        $packUnitCost = $type?->pack_cost_price ?? $product?->pack_cost_price;

        return [
            $unitCost !== null ? (float) $unitCost : null,
            $packUnitCost !== null ? (float) $packUnitCost : null,
        ];
    }

    public function createFromCart(Shop $shop, Cart $cart, ?string $note = null, ?int $memberId = null, ?int $cartOwnerTelegramId = null, OrderChannel $channel = OrderChannel::BOT): Order
    {
        if ($cart->isEmpty()) {
            throw EmptyCartException::make();
        }

        $minOrderAmount = (int) ($shop->dealer?->min_order_amount ?? 0);
        if ($minOrderAmount > 0 && $cart->total() < $minOrderAmount) {
            throw BelowMinOrderAmountException::make(
                $cart->total(),
                $minOrderAmount,
                $shop->dealer?->currency ?? Currency::UZS,
            );
        }

        if ($shop->dealer !== null && ! $this->deliveryZones->covers($shop->dealer, $shop->region, $shop->district)) {
            throw OutsideDeliveryZoneException::make($shop->region, $shop->district);
        }

        $order = DB::transaction(function () use ($shop, $cart, $note, $memberId, $cartOwnerTelegramId, $channel): Order {
            $products = $this->lockProducts($shop->dealer_id, $cart->productIds());
            $types = $this->lockTypes($cart->productTypeIds());

            $this->guardProductsAvailable($cart, $products, $types);

            // Platforma komissiyasi yaratilish paytida snapshot qilinadi —
            // keyingi stavka o'zgarishlari ushbu buyurtmaga ta'sir qilmaydi.
            // FIXED_* tiplarda buyurtma satridan foiz olinmaydi (mijoz soniga yoki
            // buyurtmaga qarab keyin hisoblanadi) — snapshot null qoldiriladi.
            $dealer = Dealer::query()
                ->select(['id', 'platform_fee_rate', 'commission_type'])
                ->whereKey($shop->dealer_id)
                ->first();

            $feeRate = ($dealer?->commission_type ?? CommissionType::TURNOVER_PERCENTAGE) === CommissionType::TURNOVER_PERCENTAGE
                ? (float) ($dealer->platform_fee_rate ?? 0)
                : null;

            $order = Order::query()->create([
                'shop_id' => $shop->id,
                'member_id' => $memberId,
                'dealer_id' => $shop->dealer_id,
                'currency' => $shop->dealer?->currency ?? Currency::UZS,
                'channel' => $channel,
                'status' => OrderStatus::PENDING,
                'total' => $cart->total(),
                'note' => $note,
                'platform_fee_rate' => $feeRate,
            ]);

            foreach ($cart as $item) {
                $product = $products->get($item->productId);
                $type = $item->productTypeId !== null ? $types->get($item->productTypeId) : null;
                [$unitCost, $packUnitCost] = $this->costSnapshot($product, $type);

                $order->items()->create([
                    'product_id' => $item->productId,
                    'product_type_id' => $item->productTypeId,
                    'product_name' => $item->productName,
                    'product_type_name' => $item->productTypeName,
                    'product_type_code' => $item->productTypeCode,
                    'price' => $item->price,
                    'pack_price' => $item->packPrice,
                    'unit_cost' => $unitCost,
                    'pack_unit_cost' => $packUnitCost,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'pack_size' => $item->packSize,
                    'pack_qty' => $item->packQty,
                ]);
            }

            // Sklad qoldiqlari endi yaratish paytida emas, balki sklad
            // dispatch qilganda kamayadi. Saldoga ham tegmaymiz —
            // qarz `deliver()` da haqiqiy yetkazilgan summa bo'yicha yoziladi.

            // `total` snapshot'ini DBga yozilgan item'lar bo'yicha qayta hisoblash —
            // cart.total() bilan farq qilishi mumkin emas, lekin yagona manba
            // sifatida items'dan olamiz, kelajakda farq xatosi bo'lmaslik uchun.
            $order->load('items');
            $itemsTotal = (int) $order->items->sum(fn ($item): int => $item->subtotal());

            if ($itemsTotal !== (int) $order->total) {
                $order->update(['total' => $itemsTotal]);
            }

            if ($cartOwnerTelegramId !== null) {
                $this->cartService->clear($cartOwnerTelegramId, $shop->id);
            }

            // Status tarixi: tizim tomonidan yaratildi (PENDING)
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PENDING,
                'changed_by_member_id' => $memberId,
                'changed_at' => $order->created_at,
            ]);

            event(new OrderCreated($order));

            return $order->load('shop', 'items.product', 'items.productType');
        });

        return $order;
    }

    public function transition(Order $order, OrderStatus $next, ?User $by = null, ?string $reason = null): Order
    {
        if ($next === OrderStatus::DELIVERED) {
            throw InvalidOrderTransitionException::from($order->status, $next);
        }

        if (! $order->status->canTransitionTo($next)) {
            throw InvalidOrderTransitionException::from($order->status, $next);
        }

        $previous = $order->status;

        $updated = DB::transaction(function () use ($order, $previous, $next, $by, $reason): Order {
            $now = now();

            $update = ['status' => $next];

            if ($next === OrderStatus::ASSEMBLING) {
                $update['assembling_at'] = $now;
            } elseif ($next === OrderStatus::DELIVERING) {
                $update['delivering_at'] = $now;
            } elseif ($next === OrderStatus::CANCELLED) {
                $update['cancelled_at'] = $now;
                $update['cancelled_by_user_id'] = $by?->id;
                $update['cancellation_reason'] = $reason;
            }

            $order->update($update);

            if ($next === OrderStatus::CANCELLED) {
                // Sklad qoldig'iga tegmaymiz — yaratishda kamaytirilmagan,
                // dispatch qilingan bo'lsa cancel taqiqlangan (status guard).
                // Saldo o'zgarmaydi — qarz hali yozilmagan.
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => $next,
                'changed_by_user_id' => $by?->id,
                'reason' => $reason,
                'changed_at' => $now,
            ]);

            return $order->refresh();
        });

        event(new OrderStatusChanged($updated, $previous, $next));

        return $updated;
    }

    /**
     * Skladchi (yoki owner) buyurtmani tayyorlaydi: pending → assembling.
     * Har bir mahsulot uchun necha dona/blok skladdan chiqarilishi yoziladi
     * va sklad qoldig'idan minus bo'ladi. Yetkazib beruvchining "carry"
     * qoldig'i `picked_qty - delivered_qty - returned_qty` formulasi bilan
     * hisoblanadi.
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, picked_qty: int|float, picked_pack_qty?: int|null}>  $pickedItems
     */
    public function assemble(Order $order, User $by, array $pickedItems = []): Order
    {
        $order->loadMissing('items');

        $isBackfill = $order->status !== OrderStatus::PENDING
            && in_array($order->status, [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING], true)
            && $order->items->every(fn ($item) => (float) ($item->picked_qty ?? 0) <= 0);

        if ($order->status !== OrderStatus::PENDING && ! $isBackfill) {
            throw InvalidOrderTransitionException::from($order->status, OrderStatus::ASSEMBLING);
        }

        $previous = $order->status;
        $pickedMap = $this->normalizePickedItems($pickedItems);

        $affectedProductIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['product_id'],
            array_values($pickedMap),
        )));

        $assembled = DB::transaction(function () use ($order, $previous, $pickedMap, $by, $isBackfill): Order {
            $order->loadMissing('items', 'shop');

            $productIds = array_values(array_unique(array_merge(
                $order->items->pluck('product_id')->all(),
                array_map(static fn (array $row): int => $row['product_id'], array_values($pickedMap)),
            )));

            $typeIds = array_values(array_unique(array_filter(array_merge(
                $order->items->pluck('product_type_id')->filter()->all(),
                array_map(static fn (array $row): ?int => $row['product_type_id'], array_values($pickedMap)),
            ), static fn ($v) => $v !== null)));

            $products = $this->lockProducts($order->dealer_id, $productIds);
            $types = $this->lockTypes($typeIds);

            $stockLines = $this->applyPickedStockDeltas($order, $pickedMap, $products, $types);
            $this->syncPickedOrderItems($order, $pickedMap, $products, $types);

            $now = now();

            if ($isBackfill) {
                if ($order->assembling_at === null) {
                    $order->update(['assembling_at' => $now]);
                }
            } else {
                $order->update([
                    'status' => OrderStatus::ASSEMBLING,
                    'assembling_at' => $now,
                ]);
            }

            $this->recordOrderStockMovement(
                order: $order,
                actor: $by,
                type: TransactionType::STOCK_OUT,
                lines: $stockLines,
                note: $isBackfill
                    ? "Buyurtma #{$order->displayNumber()} skladdan keyinroq olib chiqildi"
                    : "Buyurtma #{$order->displayNumber()} tayyorlandi",
                reason: 'sale_dispatch',
            );

            if (! $isBackfill) {
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => $previous,
                    'to_status' => OrderStatus::ASSEMBLING,
                    'changed_by_user_id' => $by->id,
                    'changed_at' => $now,
                ]);
            }

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        if (! $isBackfill) {
            event(new OrderStatusChanged($assembled, $previous, OrderStatus::ASSEMBLING));
        }

        $this->stockAlert->checkAndNotifyMany($affectedProductIds);

        return $assembled;
    }

    /**
     * Owner skladdan olib chiqilgan miqdorni (picked_qty) yetkazishdan oldin
     * tahrirlaydi. Faqat ASSEMBLING/DELIVERING statusda — buyurtma hali
     * yetkazilmagani uchun to'lov va saldoga ta'sir qilmaydi. Sklad qoldig'i
     * picked_qty farqi bo'yicha moslashtiriladi (oshsa minus, kamaysa plus).
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, picked_qty: int|float, picked_pack_qty?: int|null}>  $items
     */
    public function editPicked(Order $order, array $items, ?User $by = null): Order
    {
        if (! in_array($order->status, [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING], true)) {
            throw new \InvalidArgumentException(
                'Skladdan berilgan miqdorni faqat tayyorlanayotgan yoki yo\'ldagi buyurtmada tahrirlash mumkin'
            );
        }

        $pickedMap = $this->normalizePickedItems($items);

        $edited = DB::transaction(function () use ($order, $pickedMap, $by): Order {
            $order->loadMissing('items', 'shop');

            $productIds = array_values(array_unique(array_merge(
                $order->items->pluck('product_id')->all(),
                array_map(static fn (array $row): int => $row['product_id'], array_values($pickedMap)),
            )));
            $typeIds = array_values(array_unique(array_filter(array_merge(
                $order->items->pluck('product_type_id')->filter()->all(),
                array_map(static fn (array $row): ?int => $row['product_type_id'], array_values($pickedMap)),
            ), static fn ($v) => $v !== null)));

            $products = $this->lockProducts($order->dealer_id, $productIds);
            $types = $this->lockTypes($typeIds);

            $result = $this->applyPickedEditStockDeltas($order, $pickedMap, $products, $types);

            $this->recordOrderStockMovement(
                order: $order,
                actor: $by,
                type: TransactionType::STOCK_ADJUST,
                lines: $result['lines'],
                note: "Buyurtma #{$order->displayNumber()} skladdan berilgan tahrir",
                reason: 'picked_edit',
            );

            $this->syncPickedOrderItems($order, $pickedMap, $products, $types);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by_user_id' => $by?->id,
                'reason' => 'Skladdan berilgan miqdor tahrirlandi',
                'changed_at' => now(),
            ]);

            $this->stockAlert->checkAndNotifyMany($result['affected']);

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        return $edited;
    }

    /**
     * Buyurtmani yo'lga chiqaradi: assembling → delivering. Tovar tayyorlash
     * paytida skladdan chiqarilgan; bu yerda faqat status va vaqt yoziladi.
     * Owner/skladchi yetkazib beruvchini bir vaqtning o'zida biriktirishi
     * mumkin; dostavkachining o'zi bossa, o'ziga biriktirilgan bo'lishi shart
     * — bu OrderPolicy::dispatch() da cheklangan.
     */
    public function dispatch(Order $order, User $by, ?int $deliverymanId = null): Order
    {
        if ($order->status !== OrderStatus::ASSEMBLING) {
            throw InvalidOrderTransitionException::from($order->status, OrderStatus::DELIVERING);
        }

        if ($deliverymanId !== null && $order->deliveryman_id === null) {
            $order = $this->assignDeliveryman($order, $deliverymanId);
        }

        if ($order->deliveryman_id === null) {
            throw new OrderAssignmentException(
                'Buyurtmaga yetkazib beruvchi biriktirilmagan'
            );
        }

        $previous = $order->status;

        $dispatched = DB::transaction(function () use ($order, $previous, $by): Order {
            $now = now();

            $order->update([
                'status' => OrderStatus::DELIVERING,
                'delivering_at' => $now,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => OrderStatus::DELIVERING,
                'changed_by_user_id' => $by->id,
                'changed_at' => $now,
            ]);

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        event(new OrderStatusChanged($dispatched, $previous, OrderStatus::DELIVERING));

        return $dispatched;
    }

    /**
     * Buyurtmani bekor qiladi (sabab majburiy).
     */
    public function cancel(Order $order, User $by, string $reason): Order
    {
        return $this->transition($order, OrderStatus::CANCELLED, $by, $reason);
    }

    /**
     * Buyurtmaga yetkazib beruvchi biriktiradi yoki o'zgartiradi.
     * Faqat status DELIVERING'gacha bo'lganida ishlaydi.
     * `$deliverymanId === null` — biriktirishni bekor qiladi.
     * Bu metod sof state mutatsiyasi — chaqiruvchi (Policy yoki Controller)
     * kim chaqirayotganini tekshiradi.
     */
    public function assignDeliveryman(Order $order, ?int $deliverymanId): Order
    {
        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true)) {
            throw OrderAssignmentException::lockedAtStatus($order->status->label());
        }

        if ($deliverymanId !== null) {
            $deliveryman = User::query()
                ->where('id', $deliverymanId)
                ->where('dealer_id', $order->dealer_id)
                ->first();

            if ($deliveryman === null) {
                throw OrderAssignmentException::deliverymanNotFound();
            }

            if (! $deliveryman->isDeliveryman()) {
                throw OrderAssignmentException::notADeliveryman();
            }
        }

        $previousDeliverymanId = $order->deliveryman_id !== null ? (int) $order->deliveryman_id : null;

        $order->update([
            'deliveryman_id' => $deliverymanId,
            'assigned_at' => $deliverymanId !== null ? now() : null,
        ]);

        $fresh = $order->fresh() ?? $order;

        if ($previousDeliverymanId !== $deliverymanId) {
            event(new OrderDeliverymanChanged($fresh, $previousDeliverymanId));
        }

        return $fresh;
    }

    /**
     * Yetkazib beruvchi o'ziga biriktirilgan buyurtmadan voz kechadi.
     * Status o'zgarmaydi — boshqa yetkazib beruvchi biriktirilishi mumkin.
     */
    public function releaseSelfFromOrder(Order $order, User $by): Order
    {
        if (! $by->isDeliveryman() || $order->deliveryman_id !== $by->id) {
            throw OrderAssignmentException::notAssignedToYou();
        }

        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true)) {
            throw OrderAssignmentException::lockedAtStatus($order->status->label());
        }

        return $this->assignDeliveryman($order, null);
    }

    /**
     * Yetkazib beruvchi bo'sh (biriktirilmagan) buyurtmani o'ziga oladi.
     */
    public function selfAssignDeliveryman(Order $order, User $by): Order
    {
        if (! $by->isDeliveryman()) {
            throw OrderAssignmentException::notADeliveryman();
        }

        if ($order->deliveryman_id !== null) {
            throw OrderAssignmentException::alreadyAssigned();
        }

        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true)) {
            throw OrderAssignmentException::notInAssignableStatus($order->status->label());
        }

        return $this->assignDeliveryman($order, $by->id);
    }

    /**
     * Yetkazish: har bir mahsulot/tip uchun haqiqiy berilgan son, chegirma
     * va to'langan pulni yozadi.
     *
     * To'lov naqd va karta o'rtasida bo'lishishi mumkin: $paidCard <= $paidAmount.
     * Karta orqali to'lov bo'lsa, $cardholderName majburiy.
     *
     * Items kaliti: order_item identifikatori (product_id + product_type_id juftligi).
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, delivered_qty: int|float, delivered_pack_qty?: int|null}>  $items
     */
    public function deliver(Order $order, array $items, int $paidAmount, int $discount = 0, int $paidCard = 0, ?string $cardholderName = null, ?User $by = null): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::DELIVERED)) {
            throw InvalidOrderTransitionException::from($order->status, OrderStatus::DELIVERED);
        }

        $paidAmount = max(0, $paidAmount);
        $paidCard = max(0, min($paidCard, $paidAmount));
        $paidCash = $paidAmount - $paidCard;
        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        if ($paidCard > 0 && $cardholderName === '') {
            throw new \InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }

        $discount = max(0, $discount);
        $previous = $order->status;
        $deliveryMap = $this->normalizeDeliveryItems($items);

        $delivered = DB::transaction(function () use ($order, $previous, $deliveryMap, $paidAmount, $paidCash, $paidCard, $cardholderName, $discount, $by): Order {
            $order->loadMissing('items', 'shop');

            // Yetkazib beruvchi faqat skladdan olib chiqilgan miqdor (picked)
            // chegarasida yetkazishi mumkin. Sklad qoldig'i bu yerda
            // o'zgartirilmaydi — u dispatch'da kamaytirilgan. Yetkazilmagan
            // qoldiq (picked - delivered) sklad acceptReturn() bilan qabul
            // qilguncha yetkazib beruvchida turadi (vozvrat kutilmoqda).
            $this->syncOrderItems($order, $deliveryMap);

            $deliveredTotal = $this->computeDeliveredTotal($order);
            $effectiveDiscount = min($discount, $deliveredTotal);

            $balanceBefore = (int) $order->shop->refresh()->balance;
            $this->reconcileFinance($order, $deliveredTotal, $paidCash, $paidCard, $cardholderName, $effectiveDiscount);
            $balanceAfter = (int) $order->shop->refresh()->balance;

            $now = now();

            $order->update([
                'status' => OrderStatus::DELIVERED,
                'delivered_total' => $deliveredTotal,
                'discount' => $effectiveDiscount,
                'paid_amount' => $paidAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'delivered_at' => $now,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => OrderStatus::DELIVERED,
                'changed_by_user_id' => $by?->id,
                'changed_at' => $now,
            ]);

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        event(new OrderStatusChanged($delivered, $previous, OrderStatus::DELIVERED));

        // Sklad qoldig'i bu yerda o'zgarmadi (dispatch'da kamaytirilgan),
        // shuning uchun stock-alert tekshirilmaydi. Vozvrat qabul qilinganda
        // qoldiq qaytadi va o'sha yerda alert tekshiriladi.

        return $delivered;
    }

    /**
     * Owner faqat yetkazilgan yoki qabul qilingan buyurtmani tahrirlaydi.
     * Tovarlar (qo'shish/o'chirish/narx/miqdor), chegirma va to'lov o'zgartiriladi.
     * Eski payments qatorlari o'chiriladi, saldo qaytariladi va yangi summalar
     * bilan qayta yoziladi. Sklad qoldiqlari delivered_qty farqi bo'yicha
     * avtomatik moslashtiriladi. picked_qty yangi delivered_qty ga tenglashtiriladi,
     * carry 0 ga keltiriladi. returned_qty saqlanadi.
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, price?: float|int|null, pack_price?: float|int|null, delivered_qty: int|float, delivered_pack_qty?: int|null}>  $items
     */
    public function edit(Order $order, array $items, int $paidAmount, int $discount = 0, int $paidCard = 0, ?string $cardholderName = null, ?User $by = null): Order
    {
        if (! in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            throw new \InvalidArgumentException(
                'Tahrirlash faqat yetkazilgan yoki qabul qilingan buyurtmalarda'
            );
        }

        $paidAmount = max(0, $paidAmount);
        $paidCard = max(0, min($paidCard, $paidAmount));
        $paidCash = $paidAmount - $paidCard;
        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        if ($paidCard > 0 && $cardholderName === '') {
            throw new \InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }

        $discount = max(0, $discount);

        $edited = DB::transaction(function () use ($order, $items, $paidCash, $paidCard, $cardholderName, $discount, $by): Order {
            $order->loadMissing('items', 'shop');

            $this->financeService->revertOrderPayments($order);
            $order->shop->refresh();

            $deliveryMap = $this->normalizeDeliveryItems($items);
            $productIds = array_values(array_unique(array_merge(
                $order->items->pluck('product_id')->all(),
                array_map(static fn (array $row): int => $row['product_id'], array_values($deliveryMap)),
            )));
            $typeIds = array_values(array_unique(array_filter(array_merge(
                $order->items->pluck('product_type_id')->filter()->all(),
                array_map(static fn (array $row): ?int => $row['product_type_id'], array_values($deliveryMap)),
            ), static fn ($v) => $v !== null)));

            $products = $this->lockProducts($order->dealer_id, $productIds);
            $types = $this->lockTypes($typeIds);

            $editResult = $this->applyEditStockDeltas($order, $deliveryMap, $products, $types);
            $affectedProductIds = $editResult['affected'];

            // Stock harakatini sync'dan oldin yozamiz — sync ba'zi order_items'larni
            // o'chiradi va FK violation oldini olamiz. nullOnDelete kaskad keyin
            // transaction_details.order_item_id'ni NULL ga keltiradi.
            $this->recordOrderStockMovement(
                order: $order,
                actor: $by,
                type: TransactionType::STOCK_ADJUST,
                lines: $editResult['lines'],
                note: "Buyurtma #{$order->displayNumber()} tahrir",
                reason: 'order_edit',
            );

            $this->syncEditedOrderItems($order, $deliveryMap, $products, $types);

            $order->load('items');
            $deliveredTotal = $this->computeDeliveredTotal($order);
            $effectiveDiscount = min($discount, $deliveredTotal);

            $balanceBefore = (int) $order->shop->refresh()->balance;
            $this->reconcileFinance($order, $deliveredTotal, $paidCash, $paidCard, $cardholderName, $effectiveDiscount);
            $balanceAfter = (int) $order->shop->refresh()->balance;

            $order->update([
                'delivered_total' => $deliveredTotal,
                'discount' => $effectiveDiscount,
                'paid_amount' => $paidCash + $paidCard,
                'total' => $deliveredTotal,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by_user_id' => $by?->id,
                'reason' => 'Buyurtma tahrirlandi',
                'changed_at' => now(),
            ]);

            $this->stockAlert->checkAndNotifyMany($affectedProductIds);

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        event(new OrderEdited($edited, $by));

        return $edited;
    }

    /**
     * Edit paytida sklad qoldig'iga delivered_qty farqi bo'yicha ta'sir qilamiz.
     * Eski item yangisidan kam bo'lsa stockni minus qilamiz, ko'p bo'lsa plus.
     * O'chirilgan itemning butun delivered_qty stockga qaytariladi.
     * Yangi qo'shilgan item butun delivered_qty stockdan minus.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int, price: ?float, pack_price: ?float}>  $deliveryMap
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     * @return array{affected: list<int>, lines: list<array<string, mixed>>}
     */
    private function applyEditStockDeltas(Order $order, array $deliveryMap, Collection $products, Collection $types): array
    {
        $existing = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $existing[$key] = $item;
        }

        $affected = [];
        $lines = [];
        $keys = array_unique(array_merge(array_keys($existing), array_keys($deliveryMap)));

        foreach ($keys as $key) {
            $item = $existing[$key] ?? null;
            $row = $deliveryMap[$key] ?? null;

            $oldDelivered = $item !== null ? (float) ($item->delivered_qty ?? 0) : 0.0;
            $newDelivered = $row !== null ? (float) $row['qty'] : 0.0;

            $stockDelta = $oldDelivered - $newDelivered;

            $productId = $item?->product_id !== null ? (int) $item->product_id : ($row['product_id'] ?? null);
            $productTypeId = $item?->product_type_id !== null
                ? (int) $item->product_type_id
                : ($row['product_type_id'] ?? null);

            if ($productId === null) {
                continue;
            }

            $affected[] = $productId;

            if ($stockDelta === 0.0) {
                continue;
            }

            if ($productTypeId !== null) {
                $type = $types->get($productTypeId);

                if ($type === null) {
                    throw new \InvalidArgumentException("Mahsulot tipi topilmadi: {$productTypeId}");
                }

                $stockBefore = (float) $type->stock;
                $stockAfter = $stockBefore + $stockDelta;
                $type->stock = $stockAfter;

                ProductType::query()
                    ->whereKey($productTypeId)
                    ->update(['stock' => $stockAfter]);

                $productName = $item?->product_name ?? optional($products->get($productId))->name ?? "product #{$productId}";
                $typeName = $type->name;
            } else {
                $product = $products->get($productId);

                if ($product === null) {
                    throw new \InvalidArgumentException("Mahsulot topilmadi: {$productId}");
                }

                $stockBefore = (float) $product->stock;
                $stockAfter = $stockBefore + $stockDelta;
                $product->stock = $stockAfter;

                Product::query()
                    ->whereKey($productId)
                    ->update(['stock' => $stockAfter]);

                $productName = $item?->product_name ?? $product->name;
                $typeName = null;
            }

            $unitCost = $item?->price !== null
                ? (float) $item->price
                : ($row !== null && isset($row['price']) ? (float) $row['price'] : null);
            $packUnitCost = $item?->pack_price !== null
                ? (float) $item->pack_price
                : ($row !== null && isset($row['pack_price']) && $row['pack_price'] !== null ? (float) $row['pack_price'] : null);

            $lines[] = [
                'product_id' => $productId,
                'product_type_id' => $productTypeId,
                'order_item_id' => $item?->id,
                'product_name' => $productName,
                'product_type_name' => $typeName,
                'qty' => abs($stockDelta),
                'pack_qty' => null,
                'unit_cost' => $unitCost,
                'pack_unit_cost' => $packUnitCost,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'disposition' => null,
            ];
        }

        return [
            'affected' => array_values(array_unique($affected)),
            'lines' => $lines,
        ];
    }

    /**
     * Edit: mavjud itemlarni yangilaymiz (price, pack_price, delivered_qty,
     * qty, picked_qty = delivered, carry = 0). DeliveryMap'da yo'q bo'lganlarini
     * o'chiramiz (returned_qty bor bo'lsa saqlab qolamiz va delivered=0 qilamiz).
     * Yangilarini yaratamiz.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int, price: ?float, pack_price: ?float}>  $deliveryMap
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     */
    private function syncEditedOrderItems(Order $order, array $deliveryMap, Collection $products, Collection $types): void
    {
        $existing = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $existing[$key] = $item;
        }

        foreach ($existing as $key => $item) {
            $row = $deliveryMap[$key] ?? null;

            if ($row === null) {
                $returned = (float) ($item->returned_qty ?? 0);

                if ($returned > 0) {
                    $item->update([
                        'delivered_qty' => 0,
                        'delivered_pack_qty' => null,
                        'picked_qty' => $returned,
                        'picked_pack_qty' => $item->returned_pack_qty,
                        'qty' => 0,
                        'pack_qty' => null,
                    ]);

                    continue;
                }

                $item->delete();

                continue;
            }

            $packSize = max(1.0, (float) $item->pack_size);
            $packQty = $packSize > 1 ? (int) $row['pack_qty'] : 0;
            $deliveredQty = (float) $row['qty'];
            $returned = (float) ($item->returned_qty ?? 0);
            $returnedPack = (int) ($item->returned_pack_qty ?? 0);

            $payload = [
                'qty' => $deliveredQty,
                'pack_qty' => $packQty > 0 ? $packQty : null,
                'delivered_qty' => $deliveredQty,
                'delivered_pack_qty' => $packQty > 0 ? $packQty : null,
                'picked_qty' => $deliveredQty + $returned,
                'picked_pack_qty' => ($packQty + $returnedPack) > 0 ? $packQty + $returnedPack : null,
            ];

            if (($row['price'] ?? null) !== null) {
                $payload['price'] = (float) $row['price'];
            }

            if (($row['pack_price'] ?? null) !== null) {
                $payload['pack_price'] = (float) $row['pack_price'];
            }

            $item->update($payload);
        }

        foreach ($deliveryMap as $key => $row) {
            if (isset($existing[$key]) || $row['qty'] <= 0) {
                continue;
            }

            $product = $products->get($row['product_id']);

            if ($product === null) {
                continue;
            }

            $type = $row['product_type_id'] !== null ? $types->get($row['product_type_id']) : null;
            $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));
            $packQty = $packSize > 1 ? (int) $row['pack_qty'] : 0;
            $price = ($row['price'] ?? null) !== null
                ? (float) $row['price']
                : ($type !== null ? (float) $type->price : (float) $product->price);
            $packPrice = ($row['pack_price'] ?? null) !== null
                ? (float) $row['pack_price']
                : ($type?->pack_price !== null
                    ? (float) $type->pack_price
                    : ($product->pack_price !== null ? (float) $product->pack_price : null));

            if ($packPrice === null && $packSize > 1) {
                $packPrice = round($price * $packSize, 2);
            }

            $deliveredQty = (float) $row['qty'];
            [$unitCost, $packUnitCost] = $this->costSnapshot($product, $type);

            $order->items()->create([
                'product_id' => $product->id,
                'product_type_id' => $type?->id,
                'product_name' => $product->name,
                'product_type_name' => $type?->name,
                'product_type_code' => null,
                'price' => $price,
                'pack_price' => $packPrice,
                'unit_cost' => $unitCost,
                'pack_unit_cost' => $packUnitCost,
                'qty' => $deliveredQty,
                'pack_qty' => $packQty > 0 ? $packQty : null,
                'delivered_qty' => $deliveredQty,
                'delivered_pack_qty' => $packQty > 0 ? $packQty : null,
                'picked_qty' => $deliveredQty,
                'picked_pack_qty' => $packQty > 0 ? $packQty : null,
                'unit' => $product->unit->value,
                'pack_size' => $packSize,
            ]);
        }

        $order->load('items');
    }

    /**
     * Sklad (yoki diller) yetkazib beruvchidan qaytarilgan tovarlarni qabul qiladi.
     * Sklad qoldig'iga qo'shiladi. Buyurtma statusi o'zgarmaydi —
     * faqat order_items.returned_qty/pack_qty ortadi.
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, returned_qty: int|float, returned_pack_qty?: int|null}>  $items
     */
    public function acceptReturn(Order $order, array $items, User $by): Order
    {
        if (! in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            throw new \InvalidArgumentException(
                'Vozvrat faqat yetkazilgan yoki qabul qilingan buyurtmalardan olinadi'
            );
        }

        $returnMap = $this->normalizeReturnItems($items);

        if ($returnMap === []) {
            return $order;
        }

        $affectedProductIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['product_id'],
            array_values($returnMap),
        )));

        $result = DB::transaction(function () use ($order, $returnMap, $by): Order {
            $order->loadMissing('items');

            $stockLines = $this->applyReturnStockDeltas($order, $returnMap);
            $this->syncReturnedOrderItems($order, $returnMap);

            $this->recordOrderStockMovement(
                order: $order,
                actor: $by,
                type: TransactionType::STOCK_IN,
                lines: $stockLines,
                note: "Buyurtma #{$order->displayNumber()} yetkazishdagi vozvrat",
                reason: 'delivery_return',
            );

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by_user_id' => $by->id,
                'reason' => 'Vozvrat qabul qilindi',
                'changed_at' => now(),
            ]);

            return $order->refresh()->load('shop', 'items.product', 'items.productType');
        });

        $this->stockAlert->checkAndNotifyMany($affectedProductIds);

        return $result;
    }

    /**
     * Cart item key: "{product_id}:{product_type_id|0}".
     *
     * @param  array<int, array{product_id: int, product_type_id?: int|null, price?: float|int|null, pack_price?: float|int|null, delivered_qty: int|float, delivered_pack_qty?: int|null}>  $items
     * @return array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int, price: ?float, pack_price: ?float}>
     */
    private function normalizeDeliveryItems(array $items): array
    {
        $map = [];

        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $productTypeId = isset($row['product_type_id']) && (int) $row['product_type_id'] > 0
                ? (int) $row['product_type_id']
                : null;
            $qty = max(0.0, (float) ($row['delivered_qty'] ?? 0));
            $packQty = max(0, (int) ($row['delivered_pack_qty'] ?? 0));
            $price = array_key_exists('price', $row) && $row['price'] !== null && $row['price'] !== ''
                ? max(0.0, (float) $row['price'])
                : null;
            $packPrice = array_key_exists('pack_price', $row) && $row['pack_price'] !== null && $row['pack_price'] !== ''
                ? max(0.0, (float) $row['pack_price'])
                : null;

            if ($productId <= 0) {
                continue;
            }

            $key = $productId.':'.($productTypeId ?? 0);
            $existing = $map[$key] ?? ['product_id' => $productId, 'product_type_id' => $productTypeId, 'qty' => 0.0, 'pack_qty' => 0, 'price' => null, 'pack_price' => null];
            $map[$key] = [
                'product_id' => $productId,
                'product_type_id' => $productTypeId,
                'qty' => $existing['qty'] + $qty,
                'pack_qty' => $existing['pack_qty'] + $packQty,
                'price' => $price ?? $existing['price'],
                'pack_price' => $packPrice ?? $existing['pack_price'],
            ];
        }

        return $map;
    }

    private function itemKey(int $productId, ?int $productTypeId): string
    {
        return $productId.':'.($productTypeId ?? 0);
    }

    /**
     * @param  array<int, array{product_id: int, product_type_id?: int|null, picked_qty: int|float, picked_pack_qty?: int|null}>  $items
     * @return array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>
     */
    private function normalizePickedItems(array $items): array
    {
        return $this->normalizeQtyItems($items, 'picked_qty', 'picked_pack_qty');
    }

    /**
     * @param  array<int, array{product_id: int, product_type_id?: int|null, returned_qty: int|float, returned_pack_qty?: int|null}>  $items
     * @return array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>
     */
    private function normalizeReturnItems(array $items): array
    {
        return $this->normalizeQtyItems($items, 'returned_qty', 'returned_pack_qty');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>
     */
    private function normalizeQtyItems(array $items, string $qtyKey, string $packKey): array
    {
        $map = [];

        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $productTypeId = isset($row['product_type_id']) && (int) $row['product_type_id'] > 0
                ? (int) $row['product_type_id']
                : null;
            $qty = max(0.0, (float) ($row[$qtyKey] ?? 0));
            $packQty = max(0, (int) ($row[$packKey] ?? 0));

            if ($productId <= 0) {
                continue;
            }

            $key = $productId.':'.($productTypeId ?? 0);
            $existing = $map[$key] ?? ['product_id' => $productId, 'product_type_id' => $productTypeId, 'qty' => 0.0, 'pack_qty' => 0];
            $map[$key] = [
                'product_id' => $productId,
                'product_type_id' => $productTypeId,
                'qty' => $existing['qty'] + $qty,
                'pack_qty' => $existing['pack_qty'] + $packQty,
            ];
        }

        return $map;
    }

    /**
     * Zakas oqimi davomida bo'lgan stock harakatini transactions+details ga yozadi.
     * Lines bo'sh bo'lsa hech narsa yaratmaydi.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function recordOrderStockMovement(
        Order $order,
        ?User $actor,
        TransactionType $type,
        array $lines,
        ?string $note,
        ?string $reason,
    ): ?Transaction {
        if ($lines === []) {
            return null;
        }

        $transaction = Transaction::query()->create([
            'dealer_id' => $order->dealer_id,
            'user_id' => $actor?->id,
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
            'actor_name' => $actor?->name,
            'type' => $type,
            'note' => $note,
            'reason' => $reason,
        ]);

        $now = now();
        $detailRows = [];

        foreach ($lines as $line) {
            $detailRows[] = [
                'transaction_id' => $transaction->id,
                'product_id' => $line['product_id'],
                'product_type_id' => $line['product_type_id'] ?? null,
                'order_item_id' => $line['order_item_id'] ?? null,
                'product_name' => $line['product_name'],
                'product_type_name' => $line['product_type_name'] ?? null,
                'qty' => $line['qty'],
                'pack_qty' => $line['pack_qty'] ?? null,
                'unit_cost' => $line['unit_cost'] ?? null,
                'pack_unit_cost' => $line['pack_unit_cost'] ?? null,
                'stock_before' => $line['stock_before'],
                'stock_after' => $line['stock_after'],
                'disposition' => $line['disposition'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('transaction_details')->insert($detailRows);

        return $transaction;
    }

    /**
     * Dispatch paytida sklad qoldig'idan minus qilamiz (picked miqdor bo'yicha).
     * Har qator uchun stock_before/after qaytaradi — transactions yozish uchun.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>  $pickedMap
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     * @return list<array<string, mixed>>
     */
    private function applyPickedStockDeltas(Order $order, array $pickedMap, Collection $products, Collection $types): array
    {
        $itemsByKey = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $itemsByKey[$key] = $item;
        }

        $lines = [];

        foreach ($pickedMap as $key => $row) {
            $qty = (float) $row['qty'];

            if ($qty <= 0) {
                continue;
            }

            $productId = $row['product_id'];
            $productTypeId = $row['product_type_id'];
            $packQty = isset($row['pack_qty']) && (int) $row['pack_qty'] > 0 ? (int) $row['pack_qty'] : null;
            $item = $itemsByKey[$key] ?? null;

            if ($productTypeId !== null) {
                $type = $types->get($productTypeId);

                if ($type === null) {
                    throw InsufficientStockException::forProduct(
                        new Product(['name' => "type #{$productTypeId}", 'stock' => 0]),
                        $qty,
                    );
                }

                $stockBefore = (float) $type->stock;
                $stockAfter = $stockBefore - $qty;
                $type->stock = $stockAfter;

                ProductType::query()
                    ->whereKey($productTypeId)
                    ->update(['stock' => $stockAfter]);

                $lines[] = [
                    'product_id' => $productId,
                    'product_type_id' => $productTypeId,
                    'order_item_id' => $item?->id,
                    'product_name' => $item?->product_name ?? $type->name,
                    'product_type_name' => $type->name,
                    'qty' => $qty,
                    'pack_qty' => $packQty,
                    'unit_cost' => $item?->price !== null ? (float) $item->price : null,
                    'pack_unit_cost' => $item?->pack_price !== null ? (float) $item->pack_price : null,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'disposition' => null,
                ];

                continue;
            }

            $product = $products->get($productId);

            if ($product === null) {
                throw InsufficientStockException::forProduct(
                    new Product(['name' => "product #{$productId}", 'stock' => 0]),
                    $qty,
                );
            }

            $stockBefore = (float) $product->stock;
            $stockAfter = $stockBefore - $qty;
            $product->stock = $stockAfter;

            Product::query()
                ->whereKey($productId)
                ->update(['stock' => $stockAfter]);

            $lines[] = [
                'product_id' => $productId,
                'product_type_id' => null,
                'order_item_id' => $item?->id,
                'product_name' => $item?->product_name ?? $product->name,
                'product_type_name' => null,
                'qty' => $qty,
                'pack_qty' => $packQty,
                'unit_cost' => $item?->price !== null ? (float) $item->price : null,
                'pack_unit_cost' => $item?->pack_price !== null ? (float) $item->pack_price : null,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'disposition' => null,
            ];
        }

        return $lines;
    }

    /**
     * editPicked: sklad qoldig'iga picked_qty farqi bo'yicha ta'sir qilamiz.
     * Yangi picked eskisidan ko'p bo'lsa stockdan ayiramiz, kam bo'lsa qaytaramiz.
     * O'chirilgan (map'da yo'q) item butun picked_qty stockka qaytadi.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>  $pickedMap
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     * @return array{affected: list<int>, lines: list<array<string, mixed>>}
     */
    private function applyPickedEditStockDeltas(Order $order, array $pickedMap, Collection $products, Collection $types): array
    {
        $existing = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $existing[$key] = $item;
        }

        $affected = [];
        $lines = [];
        $keys = array_unique(array_merge(array_keys($existing), array_keys($pickedMap)));

        foreach ($keys as $key) {
            $item = $existing[$key] ?? null;
            $row = $pickedMap[$key] ?? null;

            $oldPicked = $item !== null ? (float) ($item->picked_qty ?? 0) : 0.0;
            $newPicked = $row !== null ? (float) $row['qty'] : 0.0;

            $stockDelta = $oldPicked - $newPicked;

            $productId = $item?->product_id !== null ? (int) $item->product_id : ($row['product_id'] ?? null);
            $productTypeId = $item?->product_type_id !== null
                ? (int) $item->product_type_id
                : ($row['product_type_id'] ?? null);

            if ($productId === null) {
                continue;
            }

            $affected[] = $productId;

            if ($stockDelta === 0.0) {
                continue;
            }

            if ($productTypeId !== null) {
                $type = $types->get($productTypeId);

                if ($type === null) {
                    throw new \InvalidArgumentException("Mahsulot tipi topilmadi: {$productTypeId}");
                }

                $stockBefore = (float) $type->stock;
                $stockAfter = $stockBefore + $stockDelta;
                $type->stock = $stockAfter;

                ProductType::query()
                    ->whereKey($productTypeId)
                    ->update(['stock' => $stockAfter]);

                $productName = $item?->product_name ?? optional($products->get($productId))->name ?? "product #{$productId}";
                $typeName = $type->name;
            } else {
                $product = $products->get($productId);

                if ($product === null) {
                    throw new \InvalidArgumentException("Mahsulot topilmadi: {$productId}");
                }

                $stockBefore = (float) $product->stock;
                $stockAfter = $stockBefore + $stockDelta;
                $product->stock = $stockAfter;

                Product::query()
                    ->whereKey($productId)
                    ->update(['stock' => $stockAfter]);

                $productName = $item?->product_name ?? $product->name;
                $typeName = null;
            }

            $lines[] = [
                'product_id' => $productId,
                'product_type_id' => $productTypeId,
                'order_item_id' => $item?->id,
                'product_name' => $productName,
                'product_type_name' => $typeName,
                'qty' => abs($stockDelta),
                'pack_qty' => null,
                'unit_cost' => $item?->price !== null ? (float) $item->price : null,
                'pack_unit_cost' => $item?->pack_price !== null ? (float) $item->pack_price : null,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'disposition' => null,
            ];
        }

        return [
            'affected' => array_values(array_unique($affected)),
            'lines' => $lines,
        ];
    }

    /**
     * Yetkazishdagi vozvrat qabul qilinganda sklad qoldig'iga qaytaramiz.
     * stock_before/after qaytaradi — transactions yozish uchun.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>  $returnMap
     * @return list<array<string, mixed>>
     */
    private function applyReturnStockDeltas(Order $order, array $returnMap): array
    {
        $itemsByKey = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $itemsByKey[$key] = $item;
        }

        $productIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['product_id'], array_values($returnMap))));
        $typeIds = array_values(array_unique(array_filter(array_map(
            static fn (array $r): ?int => $r['product_type_id'],
            array_values($returnMap),
        ), static fn ($v) => $v !== null)));

        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
        $types = $typeIds === []
            ? collect()
            : ProductType::query()->whereIn('id', $typeIds)->lockForUpdate()->get()->keyBy('id');

        $lines = [];

        foreach ($returnMap as $key => $row) {
            $qty = (float) $row['qty'];

            if ($qty <= 0) {
                continue;
            }

            $item = $itemsByKey[$key] ?? null;

            if ($item === null) {
                throw new \InvalidArgumentException("Vozvrat: buyurtmada bunday tovar yo'q ({$key})");
            }

            $maxReturn = $item->carryQty();

            if ($qty > $maxReturn + 0.0005) {
                throw new \InvalidArgumentException(
                    "Vozvrat miqdori ({$qty}) yetkazib beruvchidagi qoldiqdan ko'p ({$maxReturn})"
                );
            }

            $productId = $row['product_id'];
            $productTypeId = $row['product_type_id'];
            $packQty = isset($row['pack_qty']) && (int) $row['pack_qty'] > 0 ? (int) $row['pack_qty'] : null;

            if ($productTypeId !== null) {
                $type = $types->get($productTypeId);

                if ($type === null) {
                    throw new \InvalidArgumentException("Mahsulot tipi topilmadi: {$productTypeId}");
                }

                $stockBefore = (float) $type->stock;
                $stockAfter = $stockBefore + $qty;
                $type->stock = $stockAfter;

                ProductType::query()
                    ->whereKey($productTypeId)
                    ->update(['stock' => $stockAfter]);
            } else {
                $product = $products->get($productId);

                if ($product === null) {
                    throw new \InvalidArgumentException("Mahsulot topilmadi: {$productId}");
                }

                $stockBefore = (float) $product->stock;
                $stockAfter = $stockBefore + $qty;
                $product->stock = $stockAfter;

                Product::query()
                    ->whereKey($productId)
                    ->update(['stock' => $stockAfter]);
            }

            $lines[] = [
                'product_id' => $productId,
                'product_type_id' => $productTypeId,
                'order_item_id' => $item->id,
                'product_name' => $item->product_name,
                'product_type_name' => $item->product_type_name,
                'qty' => $qty,
                'pack_qty' => $packQty,
                'unit_cost' => (float) $item->price,
                'pack_unit_cost' => $item->pack_price !== null ? (float) $item->pack_price : null,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'disposition' => null,
            ];
        }

        return $lines;
    }

    /**
     * Picked miqdorni order_item'larga yozadi (dispatch).
     * Buyurtmada bo'lmagan mahsulot uchun yangi order_item yaratamiz —
     * narx katalogdan snapshot olinadi.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>  $pickedMap
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     */
    private function syncPickedOrderItems(Order $order, array $pickedMap, Collection $products, Collection $types): void
    {
        $existing = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $existing[$key] = $item;
        }

        foreach ($existing as $key => $item) {
            $row = $pickedMap[$key] ?? ['qty' => 0.0, 'pack_qty' => 0];
            $packSize = max(1.0, (float) $item->pack_size);
            $packQty = $packSize > 1 ? $row['pack_qty'] : 0;

            $item->update([
                'picked_qty' => $row['qty'],
                'picked_pack_qty' => $packQty > 0 ? $packQty : null,
            ]);
        }

        foreach ($pickedMap as $key => $row) {
            if (isset($existing[$key]) || $row['qty'] <= 0) {
                continue;
            }

            $productId = $row['product_id'];
            $productTypeId = $row['product_type_id'];
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $type = $productTypeId !== null ? $types->get($productTypeId) : null;
            $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));
            $packQty = $packSize > 1 ? $row['pack_qty'] : 0;
            $price = $type !== null ? (float) $type->price : (float) $product->price;
            $packPrice = $type?->pack_price !== null
                ? (float) $type->pack_price
                : ($product->pack_price !== null ? (float) $product->pack_price : null);

            if ($packPrice === null && $packSize > 1) {
                $packPrice = round($price * $packSize, 2);
            }

            [$unitCost, $packUnitCost] = $this->costSnapshot($product, $type);

            $order->items()->create([
                'product_id' => $product->id,
                'product_type_id' => $type?->id,
                'product_name' => $product->name,
                'product_type_name' => $type?->name,
                'product_type_code' => null,
                'price' => $price,
                'pack_price' => $packPrice,
                'unit_cost' => $unitCost,
                'pack_unit_cost' => $packUnitCost,
                'qty' => 0,
                'picked_qty' => $row['qty'],
                'picked_pack_qty' => $packQty > 0 ? $packQty : null,
                'unit' => $product->unit->value,
                'pack_size' => $packSize,
                'pack_qty' => null,
            ]);
        }

        $order->load('items');
    }

    /**
     * Returned miqdorni order_item'lar ustiga qo'shadi (acceptReturn).
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int}>  $returnMap
     */
    private function syncReturnedOrderItems(Order $order, array $returnMap): void
    {
        $itemsByKey = [];
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $itemsByKey[$key] = $item;
        }

        foreach ($returnMap as $key => $row) {
            $item = $itemsByKey[$key] ?? null;

            if ($item === null) {
                continue;
            }

            $qty = (float) $row['qty'];
            $packQty = (int) $row['pack_qty'];

            if ($qty <= 0 && $packQty <= 0) {
                continue;
            }

            $packSize = max(1.0, (float) $item->pack_size);
            $totalReturnedPack = $packSize > 1
                ? (int) ($item->returned_pack_qty ?? 0) + $packQty
                : 0;

            $item->update([
                'returned_qty' => (float) ($item->returned_qty ?? 0) + $qty,
                'returned_pack_qty' => $totalReturnedPack > 0 ? $totalReturnedPack : null,
            ]);
        }

        $order->load('items');
    }

    /**
     * Yetkazish: delivered miqdor picked'dan oshmasligi cheklanadi —
     * yetkazib beruvchi skladdan olib chiqilgan miqdordan ortiq berolmaydi.
     *
     * @param  array<string, array{product_id: int, product_type_id: ?int, qty: float, pack_qty: int, price: ?float, pack_price: ?float}>  $deliveryMap
     */
    private function syncOrderItems(Order $order, array $deliveryMap): void
    {
        foreach ($order->items as $item) {
            $key = $this->itemKey((int) $item->product_id, $item->product_type_id !== null ? (int) $item->product_type_id : null);
            $row = $deliveryMap[$key] ?? ['qty' => 0.0, 'pack_qty' => 0, 'price' => null, 'pack_price' => null];
            $packSize = max(1.0, (float) $item->pack_size);
            $packQty = $packSize > 1 ? (int) $row['pack_qty'] : 0;

            $picked = (float) ($item->picked_qty ?? 0);
            $pickedPack = (int) ($item->picked_pack_qty ?? 0);

            $deliveredQty = min((float) $row['qty'], $picked);
            $deliveredPackQty = $picked > 0 ? min($packQty, $pickedPack) : 0;

            $payload = [
                'delivered_qty' => $deliveredQty,
                'delivered_pack_qty' => $deliveredPackQty > 0 ? $deliveredPackQty : null,
            ];

            if (($row['price'] ?? null) !== null) {
                $payload['price'] = (float) $row['price'];
            }

            if (($row['pack_price'] ?? null) !== null) {
                $payload['pack_price'] = (float) $row['pack_price'];
            }

            $item->update($payload);
        }

        $order->load('items');
    }

    private function computeDeliveredTotal(Order $order): int
    {
        return (int) $order->items->sum(fn (OrderItem $item): int => $item->deliveredSubtotal());
    }

    private function reconcileFinance(Order $order, int $deliveredTotal, int $paidCash, int $paidCard, ?string $cardholderName, int $discount = 0): void
    {
        // Yetkazib berilgan paytda — to'liq summa qarzga yoziladi.
        // Yaratish vaqtida saldoga tegilmagan, shuning uchun bu yagona debit.
        if ($deliveredTotal > 0) {
            $this->financeService->debit(
                shop: $order->shop,
                amount: $deliveredTotal,
                note: "Buyurtma #{$order->displayNumber()}",
                order: $order,
            );
        }

        if ($discount > 0) {
            $this->financeService->credit(
                shop: $order->shop,
                amount: $discount,
                note: "Buyurtma #{$order->displayNumber()} chegirma",
                order: $order,
            );
        }

        if ($paidCash > 0) {
            $this->financeService->credit(
                shop: $order->shop,
                amount: $paidCash,
                note: "Buyurtma #{$order->displayNumber()} to'lov (naqd)",
                method: PaymentMethod::CASH,
                order: $order,
                deliverymanId: $order->deliveryman_id,
            );
        }

        if ($paidCard > 0) {
            $this->financeService->credit(
                shop: $order->shop,
                amount: $paidCard,
                note: "Buyurtma #{$order->displayNumber()} to'lov (karta)",
                method: PaymentMethod::CARD,
                cardholderName: $cardholderName,
                order: $order,
            );
        }
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function lockProducts(int $dealerId, array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('dealer_id', $dealerId)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, ProductType>
     */
    private function lockTypes(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return ProductType::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Mahsulotlar va tiplar mavjudligi va faolligini tekshiramiz.
     * Stock cheklanmaydi — manfiy bo'lib ketishi mumkin (qarz hisobida).
     *
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     */
    private function guardProductsAvailable(Cart $cart, Collection $products, Collection $types): void
    {
        foreach ($cart as $item) {
            $product = $products->get($item->productId);

            if ($product === null || ! $product->is_active) {
                throw ProductUnavailableException::forProduct($product, $item->productName);
            }

            if ($item->productTypeId !== null) {
                $type = $types->get($item->productTypeId);
                $label = $item->productTypeName !== null
                    ? "{$item->productName} — {$item->productTypeName}"
                    : $item->productName;

                if ($type === null || ! $type->is_active || $type->product_id !== $product->id) {
                    throw ProductUnavailableException::forProduct($product, $label);
                }
            }
        }
    }
}
