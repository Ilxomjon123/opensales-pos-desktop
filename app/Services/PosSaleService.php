<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleChannel;
use App\Enums\ShopType;
use App\Events\OrderCreated;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\PosSaleException;
use App\Exceptions\Domain\PosShiftException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * POS terminalida bitta tranzaksiya: do'kondan/zaxiradan mahsulot tanlanadi,
 * naqd va/yoki karta orqali to'lanadi (yoki qarzga yoziladi). Faqat ochiq smenada
 * ishlaydi va yaratish vaqtida darhol RECEIVED holatda yopiladi (chakana savdo
 * lifecycle'siz).
 */
final class PosSaleService
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly PromotionService $promotionService,
        private readonly StockAlertService $stockAlert,
    ) {}

    /**
     * @param  array<int, array{product_id:int, product_type_id?:?int, qty:int|float, pack_qty?:?int, price?:int|float|null, pack_price?:int|float|null}>  $items
     */
    public function create(
        PosShift $shift,
        User $cashier,
        Shop $customer,
        array $items,
        int $paidCash,
        int $paidCard,
        int $discount = 0,
        ?string $cardholderName = null,
        ?string $note = null,
    ): Order {
        if (! $shift->isOpen()) {
            throw PosShiftException::notOpen();
        }

        if ($shift->cashier_user_id !== $cashier->id) {
            throw PosShiftException::notYourShift();
        }

        if ($items === []) {
            throw PosSaleException::emptyCart();
        }

        if ($customer->dealer_id !== $shift->dealer_id) {
            throw new InvalidArgumentException('Mijoz boshqa dillerga tegishli');
        }

        $paidCash = max(0, $paidCash);
        $paidCard = max(0, $paidCard);
        $discount = max(0, $discount);

        if ($paidCard > 0 && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }
        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        $normalized = $this->normalizeItems($items);
        $productIds = array_values(array_unique(array_map(static fn ($r) => $r['product_id'], $normalized)));
        $typeIds = array_values(array_filter(array_unique(array_map(static fn ($r) => $r['product_type_id'], $normalized))));

        $order = DB::transaction(function () use ($shift, $cashier, $customer, $normalized, $paidCash, $paidCard, $discount, $cardholderName, $note, $productIds, $typeIds): Order {
            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $shift->dealer_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Collection<int, ProductType> $types */
            $types = $typeIds === []
                ? new Collection
                : ProductType::query()->whereIn('id', $typeIds)->lockForUpdate()->get()->keyBy('id');

            $itemRows = $this->buildItemRows($normalized, $products, $types);
            $subtotal = (int) array_sum(array_column($itemRows, 'subtotal'));
            $effectiveDiscount = min(max(0, $discount), $subtotal);
            $total = max(0, $subtotal - $effectiveDiscount);

            $totalPaid = $paidCash + $paidCard;
            $debt = max(0, $total - $totalPaid);

            if ($debt > 0 && $customer->type === ShopType::WALK_IN) {
                throw PosSaleException::debtRequiresCustomer();
            }

            $order = Order::query()->create([
                'shop_id' => $customer->id,
                'dealer_id' => $shift->dealer_id,
                'sale_channel' => SaleChannel::POS,
                'shift_id' => $shift->id,
                'cashier_user_id' => $cashier->id,
                'status' => OrderStatus::RECEIVED,
                'total' => $total,
                'paid_amount' => $totalPaid,
                'paid_cash' => $paidCash,
                'paid_card' => $paidCard,
                'debt_amount' => $debt,
                'discount' => $effectiveDiscount,
                'delivered_total' => $total,
                'payment_status' => OrderPaymentStatus::resolve($total, $totalPaid),
                'receipt_number' => $this->nextReceiptNumber($shift),
                'delivered_at' => now(),
                'received_at' => now(),
                'note' => $note,
            ]);

            foreach ($itemRows as $row) {
                $order->items()->create([
                    'product_id' => $row['product_id'],
                    'product_type_id' => $row['product_type_id'],
                    'product_name' => $row['product_name'],
                    'product_type_name' => $row['product_type_name'],
                    'product_type_code' => null,
                    'price' => $row['price'],
                    'pack_price' => $row['pack_price'],
                    'qty' => $row['qty'],
                    'delivered_qty' => $row['qty'],
                    'pack_qty' => $row['pack_qty'] ?: null,
                    'delivered_pack_qty' => $row['pack_qty'] ?: null,
                    'unit' => $row['unit'],
                    'pack_size' => $row['pack_size'],
                ]);

                $this->decrementStock($row['product_id'], $row['product_type_id'], (float) $row['qty']);
            }

            // Status tarixi: yaratilishda darhol RECEIVED
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::RECEIVED,
                'changed_by_user_id' => $cashier->id,
                'changed_at' => $order->created_at,
            ]);

            $this->reconcileFinance($order, $customer, $subtotal, $paidCash, $paidCard, $effectiveDiscount, $cardholderName, $shift->id);

            event(new OrderCreated($order));

            return $order->refresh()->load('shop', 'items.product', 'items.productType', 'cashier');
        });

        $this->stockAlert->checkAndNotifyMany($productIds);

        return $order;
    }

    /**
     * Qarzga yozilgan POS sotuvi uchun keyingi to'lov (kassir POS Index'dan ham qo'shishi mumkin).
     */
    public function recordCustomerPayment(Order $order, int $amount, PaymentMethod $method, ?string $cardholderName = null, ?int $shiftId = null, ?string $note = null): void
    {
        if ($order->sale_channel !== SaleChannel::POS) {
            throw new InvalidArgumentException('Faqat POS sotuvga to\'lov yozish mumkin');
        }

        $amount = max(0, $amount);
        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use ($order, $amount, $method, $cardholderName, $shiftId, $note): void {
            $order->loadMissing('shop');
            $this->financeService->credit(
                shop: $order->shop,
                amount: $amount,
                note: $note ?? "POS chek #{$order->receipt_number} to'lovi",
                method: $method,
                cardholderName: $cardholderName,
                order: $order,
                shiftId: $shiftId,
            );

            $newPaidCash = (int) $order->paid_cash + ($method === PaymentMethod::CASH ? $amount : 0);
            $newPaidCard = (int) $order->paid_card + ($method === PaymentMethod::CARD ? $amount : 0);
            $newPaid = $newPaidCash + $newPaidCard;
            $newDebt = max(0, (int) $order->total - $newPaid);

            $order->update([
                'paid_cash' => $newPaidCash,
                'paid_card' => $newPaidCard,
                'paid_amount' => $newPaid,
                'debt_amount' => $newDebt,
                'payment_status' => OrderPaymentStatus::resolve((int) $order->total, $newPaid),
            ]);
        });
    }

    /**
     * @param  array<int, array{product_id:int, product_type_id?:?int, qty:int|float, pack_qty?:?int, price?:int|float|null, pack_price?:int|float|null}>  $items
     * @return list<array{product_id:int, product_type_id:?int, qty:float, pack_qty:int, price:?float, pack_price:?float}>
     */
    private function normalizeItems(array $items): array
    {
        $map = [];
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $typeId = isset($row['product_type_id']) && (int) $row['product_type_id'] > 0
                ? (int) $row['product_type_id']
                : null;
            $qty = max(0.0, (float) ($row['qty'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $packQty = max(0, (int) ($row['pack_qty'] ?? 0));
            $price = isset($row['price']) && $row['price'] !== null && $row['price'] !== ''
                ? max(0.0, (float) $row['price']) : null;
            $packPrice = isset($row['pack_price']) && $row['pack_price'] !== null && $row['pack_price'] !== ''
                ? max(0.0, (float) $row['pack_price']) : null;

            $key = $productId.':'.($typeId ?? 0);
            $existing = $map[$key] ?? null;
            $map[$key] = [
                'product_id' => $productId,
                'product_type_id' => $typeId,
                'qty' => ($existing['qty'] ?? 0.0) + $qty,
                'pack_qty' => ($existing['pack_qty'] ?? 0) + $packQty,
                'price' => $price ?? ($existing['price'] ?? null),
                'pack_price' => $packPrice ?? ($existing['pack_price'] ?? null),
            ];
        }

        return array_values($map);
    }

    /**
     * @param  list<array{product_id:int, product_type_id:?int, qty:float, pack_qty:int, price:?float, pack_price:?float}>  $rows
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductType>  $types
     * @return list<array{product_id:int, product_type_id:?int, product_name:string, product_type_name:?string, qty:float, pack_qty:int, price:float, pack_price:?float, pack_size:float, unit:string, subtotal:int}>
     */
    private function buildItemRows(array $rows, Collection $products, Collection $types): array
    {
        $out = [];
        foreach ($rows as $row) {
            /** @var Product|null $product */
            $product = $products->get($row['product_id']);
            if ($product === null || ! $product->is_active) {
                throw ProductUnavailableException::forProduct($product, (string) $row['product_id']);
            }

            /** @var ProductType|null $type */
            $type = $row['product_type_id'] !== null ? $types->get($row['product_type_id']) : null;
            if ($row['product_type_id'] !== null && ($type === null || ! $type->is_active || $type->product_id !== $product->id)) {
                throw ProductUnavailableException::forProduct($product, $product->name);
            }

            $availableStock = $type !== null ? (float) $type->stock : (float) $product->stock;
            if ($availableStock + 1e-6 < $row['qty']) {
                throw InsufficientStockException::forProduct($product, $row['qty']);
            }

            $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));
            $effectivePrice = $row['price'] ?? (
                $type !== null ? (float) $type->price : (float) $this->promotionService->effectivePriceFor($product)
            );
            $basePackPrice = $type?->pack_price !== null
                ? (float) $type->pack_price
                : ($product->pack_price !== null ? (float) $product->pack_price : null);
            $effectivePackPrice = $row['pack_price'] ?? ($basePackPrice ?? ($packSize > 1 ? round($effectivePrice * $packSize, 2) : null));

            $packQty = $packSize > 1 ? max(0, (int) $row['pack_qty']) : 0;
            $loose = max(0.0, $row['qty'] - $packQty * $packSize);
            $subtotal = $packQty > 0 && $effectivePackPrice !== null
                ? (int) round($packQty * $effectivePackPrice + $loose * $effectivePrice)
                : (int) round($row['qty'] * $effectivePrice);

            $out[] = [
                'product_id' => $product->id,
                'product_type_id' => $type?->id,
                'product_name' => $product->name,
                'product_type_name' => $type?->name,
                'qty' => $row['qty'],
                'pack_qty' => $packQty,
                'price' => $effectivePrice,
                'pack_price' => $effectivePackPrice,
                'pack_size' => $packSize,
                'unit' => $product->unit->value,
                'subtotal' => $subtotal,
            ];
        }

        return $out;
    }

    private function decrementStock(int $productId, ?int $productTypeId, float $qty): void
    {
        // Builder::decrement int|float qabul qiladi va bindingdan foydalanadi —
        // raw SQL stringini qo'lda qurishdan xavfsizroq.
        if ($productTypeId !== null) {
            ProductType::query()->whereKey($productTypeId)->decrement('stock', $qty);

            return;
        }
        Product::query()->whereKey($productId)->decrement('stock', $qty);
    }

    /**
     * Sotuvni moliyaga yozish (OrderService::reconcileFinance pattern):
     *   balance += -subtotal + discount + paid_cash + paid_card
     *   = -(subtotal - discount - paid) = -(total - paid) = -debt
     *
     * Walk-in mijoz: qarz bo'lmaydi (yuqorida throw bilan kafolatlangan) → balance 0 da qoladi.
     * Individual mijoz qarzga: balance manfiy bo'ladi.
     */
    private function reconcileFinance(Order $order, Shop $customer, int $subtotal, int $paidCash, int $paidCard, int $discount, ?string $cardholderName, int $shiftId): void
    {
        if ($subtotal > 0) {
            $this->financeService->debit(
                shop: $customer,
                amount: $subtotal,
                note: "POS chek #{$order->receipt_number}",
                order: $order,
                shiftId: $shiftId,
            );
        }

        if ($discount > 0) {
            $this->financeService->credit(
                shop: $customer,
                amount: $discount,
                note: "POS chek #{$order->receipt_number} chegirma",
                order: $order,
                shiftId: $shiftId,
            );
        }

        if ($paidCash > 0) {
            $this->financeService->credit(
                shop: $customer,
                amount: $paidCash,
                note: "POS chek #{$order->receipt_number} naqd",
                method: PaymentMethod::CASH,
                order: $order,
                shiftId: $shiftId,
            );
        }

        if ($paidCard > 0) {
            $this->financeService->credit(
                shop: $customer,
                amount: $paidCard,
                note: "POS chek #{$order->receipt_number} karta",
                method: PaymentMethod::CARD,
                cardholderName: $cardholderName,
                order: $order,
                shiftId: $shiftId,
            );
        }
    }

    /**
     * Smena qatorini lockForUpdate bilan band qiladi, so'ng smenadagi
     * mavjud sotuv sonini ko'rib +1. Bir vaqtda kelgan sotuvlar serial.
     * Joriy tranzaksiya ichida chaqirilishi shart.
     */
    private function nextReceiptNumber(PosShift $shift): string
    {
        PosShift::query()->whereKey($shift->id)->lockForUpdate()->first();
        $count = Order::query()->forShift($shift->id)->count() + 1;

        return sprintf('%d-%05d', $shift->id, $count);
    }
}
