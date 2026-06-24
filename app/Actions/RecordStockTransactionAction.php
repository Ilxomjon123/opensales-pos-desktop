<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SupplierFinanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordStockTransactionAction
{
    public function __construct(
        private readonly SupplierFinanceService $supplierFinance,
    ) {}

    /**
     * Prixod / chiqim / tuzatish operatsiyasini yozadi.
     *
     * Diqqat:
     *  - Bitta tranzaksiya ichida `forUpdate` bilan mahsulotlar/tiplar qulflanadi —
     *    parallel zakas/prixod orasida stock buzilmaydi.
     *  - Detail satrlari va stock yangilanishi bitta DB tranzaksiyasida.
     *  - `product_type_id` berilsa qoldiq type darajasida hisoblanadi.
     *  - Bitta product har bir tipi bo'yicha alohida satr bo'lishi mumkin
     *    (lekin ayni juftlik takrorlanmasligi kerak).
     *  - STOCK_IN da supplier_id majburiy va har satrda `unit_cost` bo'lishi shart —
     *    supplier balansiga prixod qiymati DEBIT sifatida qo'shiladi (qarz).
     *
     * @param  list<array{product_id: int, product_type_id?: int|null, qty: int|float, unit_cost?: float|int|null, pack_unit_cost?: float|int|null}>  $lines
     */
    public function execute(
        User $actor,
        int $dealerId,
        TransactionType $type,
        array $lines,
        ?string $note = null,
        ?int $supplierId = null,
        int $paidAmount = 0,
        PaymentMethod $paymentMethod = PaymentMethod::CASH,
        ?string $cardholderName = null,
        bool $allowNoSupplier = false,
    ): Transaction {
        if ($lines === []) {
            throw new InvalidArgumentException('Transaction must contain at least one line.');
        }

        // Qo'lda prixodda supplier majburiy; marketplace ichki kirim uchun
        // $allowNoSupplier=true bilan supplier'siz ruxsat etiladi.
        if ($type === TransactionType::STOCK_IN && $supplierId === null && ! $allowNoSupplier) {
            throw new InvalidArgumentException('Supplier is required for stock-in transactions.');
        }

        if ($paidAmount < 0) {
            throw new InvalidArgumentException('Paid amount cannot be negative.');
        }

        $productIds = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['product_id'], $lines)));
        $typeIds = array_values(array_unique(array_filter(array_map(
            static fn (array $l): ?int => isset($l['product_type_id']) && (int) $l['product_type_id'] > 0
                ? (int) $l['product_type_id']
                : null,
            $lines,
        ), static fn ($v) => $v !== null)));

        $signatures = [];
        foreach ($lines as $l) {
            $sig = (int) $l['product_id'].':'.(isset($l['product_type_id']) && (int) $l['product_type_id'] > 0 ? (int) $l['product_type_id'] : 0);
            if (isset($signatures[$sig])) {
                throw new InvalidArgumentException('Duplicate product/type in transaction lines.');
            }
            $signatures[$sig] = true;
        }

        if ($type === TransactionType::STOCK_IN) {
            foreach ($lines as $line) {
                if (! isset($line['unit_cost']) || (float) $line['unit_cost'] <= 0.0) {
                    throw new InvalidArgumentException('Unit cost is required for stock-in transactions.');
                }
            }
        }

        return DB::transaction(function () use ($actor, $dealerId, $type, $lines, $note, $productIds, $typeIds, $supplierId, $paidAmount, $paymentMethod, $cardholderName): Transaction {
            $supplier = null;

            if ($supplierId !== null) {
                $supplier = Supplier::query()
                    ->where('id', $supplierId)
                    ->where('dealer_id', $dealerId)
                    ->lockForUpdate()
                    ->first();

                if ($supplier === null) {
                    throw new InvalidArgumentException('Supplier does not belong to this dealer.');
                }
            }

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->get(['id', 'name', 'stock', 'has_types'])
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw new InvalidArgumentException('One or more products do not belong to this dealer.');
            }

            $types = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->lockForUpdate()
                    ->get(['id', 'product_id', 'name', 'stock'])
                    ->keyBy('id');

            if ($types->count() !== count($typeIds)) {
                throw new InvalidArgumentException('One or more product types do not exist.');
            }

            $transaction = Transaction::query()->create([
                'dealer_id' => $dealerId,
                'user_id' => $actor->id,
                'supplier_id' => $supplier?->id,
                'actor_name' => $actor->name,
                'type' => $type,
                'note' => $note,
            ]);

            $now = Carbon::now();
            $detailRows = [];
            $productStockUpdates = [];
            $typeStockUpdates = [];
            $productCostUpdates = [];
            $typeCostUpdates = [];
            $totalCost = 0;

            foreach ($lines as $line) {
                $product = $products[(int) $line['product_id']];
                $qty = (float) $line['qty'];
                $typeId = isset($line['product_type_id']) && (int) $line['product_type_id'] > 0
                    ? (int) $line['product_type_id']
                    : null;
                $unitCost = isset($line['unit_cost']) ? (float) $line['unit_cost'] : null;
                $packUnitCost = isset($line['pack_unit_cost']) && $line['pack_unit_cost'] !== null && $line['pack_unit_cost'] !== ''
                    ? (float) $line['pack_unit_cost']
                    : null;

                if ($qty <= 0) {
                    throw new InvalidArgumentException("Quantity must be positive (product {$product->id}).");
                }

                if ($product->has_types && $typeId === null) {
                    throw new InvalidArgumentException("Product {$product->id} has types — type must be specified.");
                }

                if ($unitCost !== null && $qty > 0) {
                    $totalCost += (int) round($unitCost * $qty);
                }

                if ($typeId !== null) {
                    $productType = $types->get($typeId);

                    if ($productType === null || $productType->product_id !== $product->id) {
                        throw new InvalidArgumentException("Product type {$typeId} does not belong to product {$product->id}.");
                    }

                    $delta = $type === TransactionType::STOCK_OUT ? -$qty : $qty;
                    $stockBefore = (float) ($typeStockUpdates[$productType->id] ?? $productType->stock);
                    $stockAfter = $stockBefore + $delta;

                    $detailRows[] = [
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'product_type_id' => $productType->id,
                        'product_name' => $product->name,
                        'product_type_name' => $productType->name,
                        'qty' => $qty,
                        'unit_cost' => $unitCost,
                        'pack_unit_cost' => $packUnitCost,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $typeStockUpdates[$productType->id] = $stockAfter;

                    // STOCK_IN — tip darajasidagi cost_price avto-yangilanadi (oxirgi qiymat g'olib).
                    if ($type === TransactionType::STOCK_IN && $unitCost !== null) {
                        $typeCostUpdates[$productType->id] = [
                            'cost_price' => $unitCost,
                            'pack_cost_price' => $packUnitCost,
                        ];
                    }

                    continue;
                }

                $delta = $type === TransactionType::STOCK_OUT ? -$qty : $qty;
                $stockBefore = (float) ($productStockUpdates[$product->id] ?? $product->stock);
                $stockAfter = $stockBefore + $delta;

                $detailRows[] = [
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_type_id' => null,
                    'product_name' => $product->name,
                    'product_type_name' => null,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'pack_unit_cost' => $packUnitCost,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $productStockUpdates[$product->id] = $stockAfter;

                // STOCK_IN — product darajasidagi cost_price avto-yangilanadi.
                if ($type === TransactionType::STOCK_IN && $unitCost !== null) {
                    $productCostUpdates[$product->id] = [
                        'cost_price' => $unitCost,
                        'pack_cost_price' => $packUnitCost,
                    ];
                }
            }

            DB::table('transaction_details')->insert($detailRows);

            foreach ($productStockUpdates as $productId => $newStock) {
                $update = ['stock' => $newStock];

                if (isset($productCostUpdates[$productId])) {
                    $update['cost_price'] = $productCostUpdates[$productId]['cost_price'];
                    $update['pack_cost_price'] = $productCostUpdates[$productId]['pack_cost_price'];
                }

                Product::query()->where('id', $productId)->update($update);
            }

            foreach ($typeStockUpdates as $typeId => $newStock) {
                $update = ['stock' => $newStock];

                if (isset($typeCostUpdates[$typeId])) {
                    $update['cost_price'] = $typeCostUpdates[$typeId]['cost_price'];
                    $update['pack_cost_price'] = $typeCostUpdates[$typeId]['pack_cost_price'];
                }

                ProductType::query()->where('id', $typeId)->update($update);
            }

            if ($type === TransactionType::STOCK_IN && $supplier !== null && $totalCost > 0) {
                $this->supplierFinance->debit(
                    supplier: $supplier,
                    amount: $totalCost,
                    note: 'Prixod #'.$transaction->id,
                    transactionId: $transaction->id,
                );

                if ($paidAmount > 0) {
                    $this->supplierFinance->credit(
                        supplier: $supplier->refresh(),
                        amount: $paidAmount,
                        note: 'Prixod #'.$transaction->id.' uchun to\'lov',
                        method: $paymentMethod,
                        cardholderName: $cardholderName,
                        transactionId: $transaction->id,
                    );
                }
            }

            return $transaction->load('details');
        });
    }
}
