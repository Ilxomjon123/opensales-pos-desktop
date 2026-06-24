<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnReason;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\SupplierFinanceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordReturnAction
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly SupplierFinanceService $supplierFinance,
    ) {}

    /**
     * Mijozdan (mijozdan) yetkazib bo'lingan zakas bo'yicha vozvrat.
     *
     * Tartibi:
     *  - Order DELIVERED yoki RECEIVED holatda bo'lishi shart.
     *  - Har satr qiymati order_items snapshot narxidan hisoblanadi.
     *  - Disposition=restock bo'lsa stok ortadi, spoilage bo'lsa o'zgarmaydi.
     *  - Refund totali shop balansiga CREDIT bo'lib yoziladi (qarz kamayadi).
     *  - Validatsiya: bir necha vozvrat kelishi mumkin, lekin yig'indi
     *    delivered_qty/delivered_pack_qty dan oshmasligi shart.
     *
     * @param  list<array{
     *     order_item_id: int,
     *     qty: int|float,
     *     pack_qty?: int|null,
     *     disposition: ReturnDisposition|string,
     * }>  $lines
     */
    public function recordShopReturn(
        User $actor,
        int $dealerId,
        Order $order,
        array $lines,
        ReturnReason $reason,
        ?string $note = null,
        int $paidCash = 0,
        int $paidCard = 0,
        ?string $cardholderName = null,
    ): Transaction {
        if ($lines === []) {
            throw new InvalidArgumentException('Vozvratda kamida bitta satr bo\'lishi kerak.');
        }

        if ($order->dealer_id !== $dealerId) {
            throw new InvalidArgumentException('Bu zakas ushbu dillerga tegishli emas.');
        }

        if (! in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            throw new InvalidArgumentException('Vozvrat faqat yetkazilgan yoki qabul qilingan zakasdan olinadi.');
        }

        $orderItemIds = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['order_item_id'], $lines)));

        if (count($orderItemIds) !== count($lines)) {
            throw new InvalidArgumentException('Bir zakas mahsuloti vozvratda bir necha marta kelmasligi kerak.');
        }

        $paidCash = max(0, $paidCash);
        $paidCard = max(0, $paidCard);

        if ($paidCard > 0 && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy.');
        }

        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        return DB::transaction(function () use ($actor, $dealerId, $order, $lines, $reason, $note, $paidCash, $paidCard, $cardholderName, $orderItemIds): Transaction {
            $items = OrderItem::query()
                ->whereIn('id', $orderItemIds)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($orderItemIds)) {
                throw new InvalidArgumentException('Vozvrat satrlari zakasga mos kelmadi.');
            }

            $productIds = $items->pluck('product_id')->unique()->all();
            $typeIds = $items->pluck('product_type_id')->filter()->unique()->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $types = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            $alreadyReturned = DB::table('transaction_details')
                ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->whereIn('transaction_details.order_item_id', $orderItemIds)
                ->where('transactions.type', TransactionType::SHOP_RETURN->value)
                ->groupBy('transaction_details.order_item_id')
                ->selectRaw('order_item_id, SUM(qty) AS total_qty, SUM(COALESCE(pack_qty, 0)) AS total_pack_qty')
                ->get()
                ->keyBy('order_item_id');

            $transaction = Transaction::query()->create([
                'dealer_id' => $dealerId,
                'user_id' => $actor->id,
                'shop_id' => $order->shop_id,
                'order_id' => $order->id,
                'actor_name' => $actor->name,
                'type' => TransactionType::SHOP_RETURN,
                'note' => $note,
                'reason' => $reason->value,
            ]);

            $detailRows = [];
            $productStockUpdates = [];
            $typeStockUpdates = [];
            $refundTotal = 0;
            $now = now();

            foreach ($lines as $line) {
                $item = $items[(int) $line['order_item_id']];
                $disposition = $line['disposition'] instanceof ReturnDisposition
                    ? $line['disposition']
                    : ReturnDisposition::from((string) $line['disposition']);

                $qty = (float) $line['qty'];
                $packQty = isset($line['pack_qty']) && $line['pack_qty'] !== null && (int) $line['pack_qty'] > 0
                    ? (int) $line['pack_qty']
                    : null;

                if ($qty <= 0) {
                    throw new InvalidArgumentException('Vozvrat miqdori musbat bo\'lishi kerak.');
                }

                $prior = $alreadyReturned->get((int) $item->id);
                $priorQty = $prior !== null ? (float) $prior->total_qty : 0.0;
                $priorPackQty = $prior !== null ? (int) $prior->total_pack_qty : 0;

                $deliveredQty = (float) $item->delivered_qty;
                $deliveredPackQty = (int) ($item->delivered_pack_qty ?? 0);

                if ($priorQty + $qty - $deliveredQty > 0.0001) {
                    throw new InvalidArgumentException("Vozvrat miqdori yetkazilgandan oshmasligi kerak (item #{$item->id}).");
                }

                if ($packQty !== null && $priorPackQty + $packQty > $deliveredPackQty) {
                    throw new InvalidArgumentException("Blok vozvrati yetkazilgan blokdan oshmasligi kerak (item #{$item->id}).");
                }

                $subtotal = $this->lineSubtotal($item, $qty, $packQty);
                $refundTotal += $subtotal;

                $unitCost = (float) $item->price;
                $packUnitCost = $item->pack_price !== null ? (float) $item->pack_price : null;

                if ($disposition === ReturnDisposition::RESTOCK) {
                    if ($item->product_type_id !== null) {
                        $type = $types->get((int) $item->product_type_id);

                        if ($type === null) {
                            throw new InvalidArgumentException('Mahsulot tipi topilmadi.');
                        }

                        $stockBefore = (float) ($typeStockUpdates[$type->id] ?? $type->stock);
                        $stockAfter = $stockBefore + $qty;
                        $typeStockUpdates[$type->id] = $stockAfter;
                    } else {
                        $product = $products->get((int) $item->product_id);

                        if ($product === null) {
                            throw new InvalidArgumentException('Mahsulot topilmadi.');
                        }

                        $stockBefore = (float) ($productStockUpdates[$product->id] ?? $product->stock);
                        $stockAfter = $stockBefore + $qty;
                        $productStockUpdates[$product->id] = $stockAfter;
                    }
                } else {
                    $stockBefore = $item->product_type_id !== null
                        ? (float) (($typeStockUpdates[$item->product_type_id] ?? null) ?? optional($types->get((int) $item->product_type_id))->stock ?? 0)
                        : (float) (($productStockUpdates[$item->product_id] ?? null) ?? optional($products->get((int) $item->product_id))->stock ?? 0);
                    $stockAfter = $stockBefore;
                }

                $detailRows[] = [
                    'transaction_id' => $transaction->id,
                    'product_id' => $item->product_id,
                    'product_type_id' => $item->product_type_id,
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_type_name' => $item->product_type_name,
                    'qty' => $qty,
                    'pack_qty' => $packQty,
                    'unit_cost' => $unitCost,
                    'pack_unit_cost' => $packUnitCost,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'disposition' => $disposition->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('transaction_details')->insert($detailRows);

            foreach ($productStockUpdates as $productId => $newStock) {
                Product::query()->where('id', $productId)->update(['stock' => $newStock]);
            }

            foreach ($typeStockUpdates as $typeId => $newStock) {
                ProductType::query()->where('id', $typeId)->update(['stock' => $newStock]);
            }

            // Refundni 1-3 alohida CREDIT yozuvi sifatida qayd qilamiz:
            //  1) Naqd qaytarilgan qism (PaymentMethod::CASH)
            //  2) Karta orqali qaytarilgan qism (PaymentMethod::CARD + cardholder)
            //  3) Balansga yozilgan qoldiq (mijoz qarzini kamaytirish — PaymentMethod::CASH cosmetic)
            $cashPortion = min($paidCash, $refundTotal);
            $remainder = $refundTotal - $cashPortion;
            $cardPortion = min($paidCard, $remainder);
            $balanceOnly = $remainder - $cardPortion;

            if ($cashPortion > 0) {
                $this->finance->credit(
                    shop: $order->shop,
                    amount: $cashPortion,
                    note: "Vozvrat #{$transaction->id} naqd qaytarish",
                    method: PaymentMethod::CASH,
                    order: $order,
                );
            }

            if ($cardPortion > 0) {
                $this->finance->credit(
                    shop: $order->shop,
                    amount: $cardPortion,
                    note: "Vozvrat #{$transaction->id} karta qaytarish",
                    method: PaymentMethod::CARD,
                    cardholderName: $cardholderName,
                    order: $order,
                );
            }

            if ($balanceOnly > 0) {
                $this->finance->credit(
                    shop: $order->shop,
                    amount: $balanceOnly,
                    note: "Vozvrat #{$transaction->id} (Buyurtma #{$order->displayNumber()})",
                    method: PaymentMethod::CASH,
                    order: $order,
                );
            }

            return $transaction->load('details');
        });
    }

    /**
     * Mijozdan vozvrat (buyurtmasiz, erkin shaklda).
     *
     * Tartibi:
     *  - Mahsulot/tip ro'yxati erkin; order bilan bog'liq emas (order_item_id = null).
     *  - unit_cost dealer tomonidan kiritiladi (default — joriy product narxi).
     *  - Disposition=restock bo'lsa stok ortadi, spoilage bo'lsa o'zgarmaydi.
     *  - Refund totali shop balansiga CREDIT bo'lib yoziladi.
     *
     * @param  list<array{
     *     product_id: int,
     *     product_type_id?: int|null,
     *     qty: int|float,
     *     pack_qty?: int|null,
     *     unit_cost: int|float,
     *     pack_unit_cost?: int|float|null,
     *     disposition: ReturnDisposition|string,
     * }>  $lines
     */
    public function recordShopReturnFreeform(
        User $actor,
        int $dealerId,
        Shop $shop,
        array $lines,
        ReturnReason $reason,
        ?string $note = null,
        int $paidCash = 0,
        int $paidCard = 0,
        ?string $cardholderName = null,
    ): Transaction {
        if ($lines === []) {
            throw new InvalidArgumentException('Vozvratda kamida bitta satr bo\'lishi kerak.');
        }

        if ($shop->dealer_id !== $dealerId) {
            throw new InvalidArgumentException('Mijoz ushbu dillerga tegishli emas.');
        }

        $signatures = [];

        foreach ($lines as $line) {
            $sig = (int) $line['product_id'].':'.(isset($line['product_type_id']) && (int) $line['product_type_id'] > 0 ? (int) $line['product_type_id'] : 0);

            if (isset($signatures[$sig])) {
                throw new InvalidArgumentException('Bir mahsulot/tip bir necha marta kelmasligi kerak.');
            }

            $signatures[$sig] = true;

            if ((float) $line['qty'] <= 0) {
                throw new InvalidArgumentException('Vozvrat miqdori musbat bo\'lishi kerak.');
            }

            if (! isset($line['unit_cost']) || (float) $line['unit_cost'] < 0) {
                throw new InvalidArgumentException('Tannarx manfiy bo\'lmasligi kerak.');
            }
        }

        $paidCash = max(0, $paidCash);
        $paidCard = max(0, $paidCard);

        if ($paidCard > 0 && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy.');
        }

        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        return DB::transaction(function () use ($actor, $dealerId, $shop, $lines, $reason, $note, $paidCash, $paidCard, $cardholderName): Transaction {
            $productIds = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['product_id'], $lines)));
            $typeIds = array_values(array_unique(array_filter(array_map(
                static fn (array $l): ?int => isset($l['product_type_id']) && (int) $l['product_type_id'] > 0 ? (int) $l['product_type_id'] : null,
                $lines,
            ), static fn ($v) => $v !== null)));

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->get(['id', 'name', 'stock', 'has_types'])
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw new InvalidArgumentException('Mahsulot dillerga tegishli emas.');
            }

            $types = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->lockForUpdate()
                    ->get(['id', 'product_id', 'name', 'stock'])
                    ->keyBy('id');

            if ($types->count() !== count($typeIds)) {
                throw new InvalidArgumentException('Mahsulot tipi topilmadi.');
            }

            $transaction = Transaction::query()->create([
                'dealer_id' => $dealerId,
                'user_id' => $actor->id,
                'shop_id' => $shop->id,
                'order_id' => null,
                'actor_name' => $actor->name,
                'type' => TransactionType::SHOP_RETURN,
                'note' => $note,
                'reason' => $reason->value,
            ]);

            $detailRows = [];
            $productStockUpdates = [];
            $typeStockUpdates = [];
            $refundTotal = 0;
            $now = now();

            foreach ($lines as $line) {
                $product = $products[(int) $line['product_id']];
                $typeId = isset($line['product_type_id']) && (int) $line['product_type_id'] > 0
                    ? (int) $line['product_type_id']
                    : null;
                $qty = (float) $line['qty'];
                $packQty = isset($line['pack_qty']) && $line['pack_qty'] !== null && (int) $line['pack_qty'] > 0
                    ? (int) $line['pack_qty']
                    : null;
                $unitCost = (float) $line['unit_cost'];
                $packUnitCost = isset($line['pack_unit_cost']) && $line['pack_unit_cost'] !== null && $line['pack_unit_cost'] !== ''
                    ? (float) $line['pack_unit_cost']
                    : null;
                $disposition = $line['disposition'] instanceof ReturnDisposition
                    ? $line['disposition']
                    : ReturnDisposition::from((string) $line['disposition']);

                if ($product->has_types && $typeId === null) {
                    throw new InvalidArgumentException("Mahsulot #{$product->id} uchun tip tanlanishi shart.");
                }

                if ($typeId !== null) {
                    $type = $types->get($typeId);

                    if ($type === null || (int) $type->product_id !== (int) $product->id) {
                        throw new InvalidArgumentException("Tip #{$typeId} mahsulot #{$product->id} ga tegishli emas.");
                    }

                    $stockBefore = (float) ($typeStockUpdates[$type->id] ?? $type->stock);
                } else {
                    $stockBefore = (float) ($productStockUpdates[$product->id] ?? $product->stock);
                }

                if ($disposition === ReturnDisposition::RESTOCK) {
                    $stockAfter = $stockBefore + $qty;

                    if ($typeId !== null) {
                        $typeStockUpdates[$typeId] = $stockAfter;
                    } else {
                        $productStockUpdates[$product->id] = $stockAfter;
                    }
                } else {
                    $stockAfter = $stockBefore;
                }

                $refundTotal += (int) round($qty * $unitCost);

                $detailRows[] = [
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_type_id' => $typeId,
                    'order_item_id' => null,
                    'product_name' => $product->name,
                    'product_type_name' => $typeId !== null ? optional($types->get($typeId))->name : null,
                    'qty' => $qty,
                    'pack_qty' => $packQty,
                    'unit_cost' => $unitCost,
                    'pack_unit_cost' => $packUnitCost,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'disposition' => $disposition->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('transaction_details')->insert($detailRows);

            foreach ($productStockUpdates as $productId => $newStock) {
                Product::query()->where('id', $productId)->update(['stock' => $newStock]);
            }

            foreach ($typeStockUpdates as $typeId => $newStock) {
                ProductType::query()->where('id', $typeId)->update(['stock' => $newStock]);
            }

            $cashPortion = min($paidCash, $refundTotal);
            $remainder = $refundTotal - $cashPortion;
            $cardPortion = min($paidCard, $remainder);
            $balanceOnly = $remainder - $cardPortion;

            if ($cashPortion > 0) {
                $this->finance->credit(
                    shop: $shop,
                    amount: $cashPortion,
                    note: "Vozvrat #{$transaction->id} naqd qaytarish",
                    method: PaymentMethod::CASH,
                );
            }

            if ($cardPortion > 0) {
                $this->finance->credit(
                    shop: $shop,
                    amount: $cardPortion,
                    note: "Vozvrat #{$transaction->id} karta qaytarish",
                    method: PaymentMethod::CARD,
                    cardholderName: $cardholderName,
                );
            }

            if ($balanceOnly > 0) {
                $this->finance->credit(
                    shop: $shop,
                    amount: $balanceOnly,
                    note: "Vozvrat #{$transaction->id}",
                    method: PaymentMethod::CASH,
                );
            }

            return $transaction->load('details');
        });
    }

    /**
     * Ta'minotchiga vozvrat: skladdagi tovardan yetkazib beruvchiga qaytarish.
     *
     * Tartibi:
     *  - Har satrda product/type lockForUpdate; mavjud stokdan oshmaslik tekshiruvi.
     *  - unit_cost majburiy — supplier balansiga shu summa CREDIT bo'lib yoziladi
     *    (qarz kamayadi).
     *  - Transaction.type = SUPPLIER_RETURN. Details qatorida disposition saqlanmaydi.
     *
     * @param  list<array{
     *     product_id: int,
     *     product_type_id?: int|null,
     *     qty: int|float,
     *     unit_cost: int|float,
     *     pack_unit_cost?: int|float|null,
     * }>  $lines
     */
    public function recordSupplierReturn(
        User $actor,
        int $dealerId,
        Supplier $supplier,
        array $lines,
        ReturnReason $reason,
        ?string $note = null,
        int $paidCash = 0,
        int $paidCard = 0,
        ?string $cardholderName = null,
    ): Transaction {
        if ($lines === []) {
            throw new InvalidArgumentException('Vozvratda kamida bitta satr bo\'lishi kerak.');
        }

        if ($supplier->dealer_id !== $dealerId) {
            throw new InvalidArgumentException('Ta\'minotchi ushbu dillerga tegishli emas.');
        }

        $signatures = [];

        foreach ($lines as $line) {
            $sig = (int) $line['product_id'].':'.(isset($line['product_type_id']) && (int) $line['product_type_id'] > 0 ? (int) $line['product_type_id'] : 0);

            if (isset($signatures[$sig])) {
                throw new InvalidArgumentException('Bir mahsulot/tip bir necha marta kelmasligi kerak.');
            }

            $signatures[$sig] = true;

            if ((float) $line['qty'] <= 0) {
                throw new InvalidArgumentException('Vozvrat miqdori musbat bo\'lishi kerak.');
            }

            if (! isset($line['unit_cost']) || (float) $line['unit_cost'] <= 0) {
                throw new InvalidArgumentException('Tannarx majburiy.');
            }
        }

        $paidCash = max(0, $paidCash);
        $paidCard = max(0, $paidCard);

        if ($paidCard > 0 && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy.');
        }

        $cardholderName = $paidCard > 0 ? trim((string) $cardholderName) : null;

        return DB::transaction(function () use ($actor, $dealerId, $supplier, $lines, $reason, $note, $paidCash, $paidCard, $cardholderName): Transaction {
            $productIds = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['product_id'], $lines)));
            $typeIds = array_values(array_unique(array_filter(array_map(
                static fn (array $l): ?int => isset($l['product_type_id']) && (int) $l['product_type_id'] > 0 ? (int) $l['product_type_id'] : null,
                $lines,
            ), static fn ($v) => $v !== null)));

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->get(['id', 'name', 'stock', 'has_types'])
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw new InvalidArgumentException('Mahsulot dillerga tegishli emas.');
            }

            $types = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->lockForUpdate()
                    ->get(['id', 'product_id', 'name', 'stock'])
                    ->keyBy('id');

            if ($types->count() !== count($typeIds)) {
                throw new InvalidArgumentException('Mahsulot tipi topilmadi.');
            }

            $transaction = Transaction::query()->create([
                'dealer_id' => $dealerId,
                'user_id' => $actor->id,
                'supplier_id' => $supplier->id,
                'actor_name' => $actor->name,
                'type' => TransactionType::SUPPLIER_RETURN,
                'note' => $note,
                'reason' => $reason->value,
            ]);

            $detailRows = [];
            $productStockUpdates = [];
            $typeStockUpdates = [];
            $totalCost = 0;
            $now = now();

            foreach ($lines as $line) {
                $product = $products[(int) $line['product_id']];
                $typeId = isset($line['product_type_id']) && (int) $line['product_type_id'] > 0
                    ? (int) $line['product_type_id']
                    : null;
                $qty = (float) $line['qty'];
                $unitCost = (float) $line['unit_cost'];
                $packUnitCost = isset($line['pack_unit_cost']) && $line['pack_unit_cost'] !== null && $line['pack_unit_cost'] !== ''
                    ? (float) $line['pack_unit_cost']
                    : null;

                if ($product->has_types && $typeId === null) {
                    throw new InvalidArgumentException("Mahsulot #{$product->id} uchun tip tanlanishi shart.");
                }

                $totalCost += (int) round($unitCost * $qty);

                if ($typeId !== null) {
                    $type = $types->get($typeId);

                    if ($type === null || (int) $type->product_id !== (int) $product->id) {
                        throw new InvalidArgumentException("Tip #{$typeId} mahsulot #{$product->id} ga tegishli emas.");
                    }

                    $stockBefore = (float) ($typeStockUpdates[$type->id] ?? $type->stock);
                    $stockAfter = $stockBefore - $qty;

                    if ($stockAfter < 0) {
                        throw new InvalidArgumentException("Yetarli stok yo'q: {$product->name} ({$type->name}).");
                    }

                    $typeStockUpdates[$type->id] = $stockAfter;
                } else {
                    $stockBefore = (float) ($productStockUpdates[$product->id] ?? $product->stock);
                    $stockAfter = $stockBefore - $qty;

                    if ($stockAfter < 0) {
                        throw new InvalidArgumentException("Yetarli stok yo'q: {$product->name}.");
                    }

                    $productStockUpdates[$product->id] = $stockAfter;
                }

                $detailRows[] = [
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_type_id' => $typeId,
                    'order_item_id' => null,
                    'product_name' => $product->name,
                    'product_type_name' => $typeId !== null ? optional($types->get($typeId))->name : null,
                    'qty' => $qty,
                    'pack_qty' => null,
                    'unit_cost' => $unitCost,
                    'pack_unit_cost' => $packUnitCost,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'disposition' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('transaction_details')->insert($detailRows);

            foreach ($productStockUpdates as $productId => $newStock) {
                Product::query()->where('id', $productId)->update(['stock' => $newStock]);
            }

            foreach ($typeStockUpdates as $typeId => $newStock) {
                ProductType::query()->where('id', $typeId)->update(['stock' => $newStock]);
            }

            if ($totalCost > 0) {
                $this->supplierFinance->credit(
                    supplier: $supplier->refresh(),
                    amount: $totalCost,
                    note: "Vozvrat #{$transaction->id}",
                    transactionId: $transaction->id,
                );
            }

            // Vozvrat paytida diller supplier'dan naqd/karta orqali jismoniy
            // pul refund olishi mumkin — bu yangi qarz hisobida DEBIT yoziladi.
            if ($paidCash > 0) {
                $this->supplierFinance->debit(
                    supplier: $supplier->refresh(),
                    amount: $paidCash,
                    note: "Vozvrat #{$transaction->id} naqd refund",
                    method: PaymentMethod::CASH,
                    transactionId: $transaction->id,
                );
            }

            if ($paidCard > 0) {
                $this->supplierFinance->debit(
                    supplier: $supplier->refresh(),
                    amount: $paidCard,
                    note: "Vozvrat #{$transaction->id} karta refund",
                    method: PaymentMethod::CARD,
                    cardholderName: $cardholderName,
                    transactionId: $transaction->id,
                );
            }

            return $transaction->load('details');
        });
    }

    /**
     * order_item snapshot narxidan vozvrat satri summasini hisoblaydi.
     * pack_qty berilgan va pack_price snapshot mavjud bo'lsa — lossless yig'iladi.
     */
    private function lineSubtotal(OrderItem $item, float $qty, ?int $packQty): int
    {
        $price = (float) $item->price;
        $packSize = max(1.0, (float) $item->pack_size);
        $packs = max(0, $packQty ?? 0);

        if ($packs > 0 && $item->pack_price !== null && $packSize > 1) {
            $loose = max(0.0, $qty - $packs * $packSize);

            return (int) round($packs * (float) $item->pack_price + $loose * $price);
        }

        return (int) round($qty * $price);
    }
}
