<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Actions\RecordReturnAction;
use App\Enums\OrderStatus;
use App\Enums\ReturnReason;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreShopReturnFreeformRequest;
use App\Http\Requests\Dealer\StoreShopReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class ShopReturnController extends Controller
{
    public function __construct(
        private readonly RecordReturnAction $recordReturn,
    ) {}

    public function storeFreeform(StoreShopReturnFreeformRequest $request, Shop $shop): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;

        if ($shop->dealer_id !== $dealerId) {
            abort(404);
        }

        $data = $request->validated();

        try {
            $this->recordReturn->recordShopReturnFreeform(
                actor: $request->user(),
                dealerId: $dealerId,
                shop: $shop,
                lines: $data['items'],
                reason: ReturnReason::from($data['reason']),
                note: $data['note'] ?? null,
                paidCash: $request->paidCash(),
                paidCard: $request->paidCard(),
                cardholderName: $data['cardholder_name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('status', 'Vozvrat yozildi');
    }

    public function store(StoreShopReturnRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;

        if ($order->dealer_id !== $dealerId) {
            abort(404);
        }

        $data = $request->validated();

        try {
            $this->recordReturn->recordShopReturn(
                actor: $request->user(),
                dealerId: $dealerId,
                order: $order->load('shop'),
                lines: $data['items'],
                reason: ReturnReason::from($data['reason']),
                note: $data['note'] ?? null,
                paidCash: $request->paidCash(),
                paidCard: $request->paidCard(),
                cardholderName: $data['cardholder_name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('status', 'Vozvrat yozildi');
    }

    /**
     * Tanlangan mijoz uchun vozvrat qabul qilish mumkin bo'lgan zakaslar
     * ro'yxatini qaytaradi (DELIVERED yoki RECEIVED, qisman vozvrat hisob bilan).
     */
    public function returnableOrders(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;

        if ($shop->dealer_id !== $dealerId) {
            abort(404);
        }

        $orders = Order::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', [OrderStatus::DELIVERED, OrderStatus::RECEIVED])
            ->with([
                'items' => function ($q): void {
                    $q->where('delivered_qty', '>', 0);
                },
                'shop:id,name,balance',
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        $orderItemIds = $orders->flatMap(fn (Order $o) => $o->items->pluck('id'))->all();

        $returnedAggregates = $orderItemIds === []
            ? collect()
            : OrderItem::query()
                ->whereIn('order_items.id', $orderItemIds)
                ->leftJoin('transaction_details', 'transaction_details.order_item_id', '=', 'order_items.id')
                ->leftJoin('transactions', function ($join): void {
                    $join->on('transactions.id', '=', 'transaction_details.transaction_id')
                        ->where('transactions.type', '=', TransactionType::SHOP_RETURN->value);
                })
                ->groupBy('order_items.id')
                ->selectRaw('order_items.id AS id, COALESCE(SUM(transaction_details.qty), 0) AS returned_total, COALESCE(SUM(transaction_details.pack_qty), 0) AS returned_pack_total')
                ->get()
                ->keyBy('id');

        $payload = $orders
            ->filter(function (Order $order) use ($returnedAggregates): bool {
                return $order->items->contains(function (OrderItem $item) use ($returnedAggregates): bool {
                    $returned = (float) ($returnedAggregates[$item->id]->returned_total ?? 0);

                    return ((float) $item->delivered_qty) - $returned > 0.0001;
                });
            })
            ->map(function (Order $order) use ($returnedAggregates): array {
                return [
                    'id' => $order->id,
                    'number' => $order->displayNumber(),
                    'status' => $order->status->value,
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                    'total' => (int) $order->total,
                    'shop' => $order->shop ? [
                        'id' => $order->shop->id,
                        'name' => $order->shop->name,
                        'balance' => (int) $order->shop->balance,
                    ] : null,
                    'items' => $order->items->map(function (OrderItem $item) use ($returnedAggregates): array {
                        $returnedQty = (float) ($returnedAggregates[$item->id]->returned_total ?? 0);
                        $returnedPackQty = (int) ($returnedAggregates[$item->id]->returned_pack_total ?? 0);

                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_type_id' => $item->product_type_id,
                            'product_name' => $item->product_name,
                            'product_type_name' => $item->product_type_name,
                            'price' => (float) $item->price,
                            'pack_price' => $item->pack_price !== null ? (float) $item->pack_price : null,
                            'qty' => (float) $item->qty,
                            'delivered_qty' => (float) $item->delivered_qty,
                            'delivered_pack_qty' => $item->delivered_pack_qty,
                            'returned_qty' => $returnedQty,
                            'returned_pack_qty' => $returnedPackQty,
                            'pack_size' => (float) $item->pack_size,
                            'unit' => $item->unit?->value,
                        ];
                    })->values()->all(),
                ];
            })
            ->values();

        return response()->json(['data' => $payload]);
    }
}
