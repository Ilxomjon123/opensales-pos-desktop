<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHUNK = 500;

    private const BATCH_INSERT = 1000;

    public function up(): void
    {
        $this->backfillSaleDispatch();
        $this->backfillDeliveryReturn();
    }

    public function down(): void
    {
        DB::table('transactions')
            ->whereIn('reason', ['sale_dispatch', 'delivery_return'])
            ->where('actor_name', '[backfill]')
            ->delete();
    }

    /**
     * Yetkazib bo'lingan zakaslar uchun picked_qty bo'yicha STOCK_OUT yozuvi.
     * Har zakas bitta transaction, ichida har order_item alohida detail satr.
     */
    private function backfillSaleDispatch(): void
    {
        DB::table('orders')
            ->whereNotNull('delivering_at')
            ->whereNotIn('status', ['pending', 'assembling', 'cancelled'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($orders): void {
                $orderIds = $orders->pluck('id')->all();

                $itemsByOrder = DB::table('order_items')
                    ->whereIn('order_id', $orderIds)
                    ->where('picked_qty', '>', 0)
                    ->get()
                    ->groupBy('order_id');

                if ($itemsByOrder->isEmpty()) {
                    return;
                }

                $this->insertOrderTransactions(
                    orders: $orders,
                    itemsByOrder: $itemsByOrder,
                    type: 'stock_out',
                    reason: 'sale_dispatch',
                    notePrefix: 'dispatch',
                    timeColumn: 'delivering_at',
                    qtyColumn: 'picked_qty',
                    packColumn: 'picked_pack_qty',
                );
            });
    }

    /**
     * Yetkazishdagi vozvrat (deliveryman returned at delivery) — STOCK_IN.
     */
    private function backfillDeliveryReturn(): void
    {
        DB::table('orders')
            ->whereIn('status', ['delivered', 'received'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($orders): void {
                $orderIds = $orders->pluck('id')->all();

                $itemsByOrder = DB::table('order_items')
                    ->whereIn('order_id', $orderIds)
                    ->where('returned_qty', '>', 0)
                    ->get()
                    ->groupBy('order_id');

                if ($itemsByOrder->isEmpty()) {
                    return;
                }

                $this->insertOrderTransactions(
                    orders: $orders,
                    itemsByOrder: $itemsByOrder,
                    type: 'stock_in',
                    reason: 'delivery_return',
                    notePrefix: 'yetkazishdagi vozvrat',
                    timeColumn: 'received_at',
                    fallbackTimeColumn: 'delivered_at',
                    qtyColumn: 'returned_qty',
                    packColumn: 'returned_pack_qty',
                );
            });
    }

    /**
     * @param  Collection<int, object>  $orders
     * @param  Collection<int, Collection<int, object>>  $itemsByOrder
     */
    private function insertOrderTransactions(
        Collection $orders,
        Collection $itemsByOrder,
        string $type,
        string $reason,
        string $notePrefix,
        string $timeColumn,
        string $qtyColumn,
        string $packColumn,
        ?string $fallbackTimeColumn = null,
    ): void {
        $now = Carbon::now();
        $detailRowBuffer = [];

        foreach ($orders as $order) {
            $items = $itemsByOrder->get($order->id);

            if ($items === null || $items->isEmpty()) {
                continue;
            }

            $when = $order->{$timeColumn}
                ?? ($fallbackTimeColumn !== null ? $order->{$fallbackTimeColumn} : null)
                ?? $now;

            $txId = DB::table('transactions')->insertGetId([
                'dealer_id' => $order->dealer_id,
                'user_id' => null,
                'supplier_id' => null,
                'shop_id' => $order->shop_id,
                'order_id' => $order->id,
                'actor_name' => '[backfill]',
                'type' => $type,
                'note' => "Buyurtma #{$order->number} {$notePrefix} (backfill)",
                'reason' => $reason,
                'created_at' => $when,
                'updated_at' => $when,
            ]);

            foreach ($items as $item) {
                $qty = (float) ($item->{$qtyColumn} ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $detailRowBuffer[] = [
                    'transaction_id' => $txId,
                    'product_id' => $item->product_id,
                    'product_type_id' => $item->product_type_id,
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_type_name' => $item->product_type_name,
                    'qty' => $qty,
                    'pack_qty' => $item->{$packColumn},
                    'unit_cost' => $item->price,
                    'pack_unit_cost' => $item->pack_price,
                    'stock_before' => null,
                    'stock_after' => null,
                    'disposition' => null,
                    'created_at' => $when,
                    'updated_at' => $when,
                ];

                if (count($detailRowBuffer) >= self::BATCH_INSERT) {
                    DB::table('transaction_details')->insert($detailRowBuffer);
                    $detailRowBuffer = [];
                }
            }
        }

        if ($detailRowBuffer !== []) {
            DB::table('transaction_details')->insert($detailRowBuffer);
        }
    }
};
