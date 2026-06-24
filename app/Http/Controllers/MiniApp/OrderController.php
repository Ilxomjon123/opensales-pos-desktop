<?php

declare(strict_types=1);

namespace App\Http\Controllers\MiniApp;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\ReorderService;
use App\Support\Dto\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OrderController extends Controller
{
    public function __construct(private readonly ReorderService $reorder) {}

    public function show(Request $request, Dealer $dealer, Order $order): JsonResponse|OrderResource
    {
        $shop = $this->shop($request);

        if ($shop === null || $order->shop_id !== $shop->id || $order->dealer_id !== $dealer->id) {
            return response()->json(['message' => 'Buyurtma topilmadi'], 404);
        }

        $order->load(['items.product', 'deliveryman', 'messages']);

        return OrderResource::make($order);
    }

    public function receive(Request $request, Dealer $dealer, Order $order): JsonResponse
    {
        $shop = $this->shop($request);
        $member = $this->member($request);

        if ($shop === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        if ($order->shop_id !== $shop->id || $order->dealer_id !== $dealer->id) {
            return response()->json(['message' => 'Buyurtma topilmadi'], 404);
        }

        if ($order->status !== OrderStatus::DELIVERED) {
            return response()->json(['message' => 'Faqat yetkazilgan buyurtmani qabul qilish mumkin'], 422);
        }

        if ($order->received_at !== null) {
            return response()->json(['message' => 'Buyurtma allaqachon qabul qilingan'], 422);
        }

        DB::transaction(function () use ($order, $member): void {
            $previous = $order->status;
            $now = now();

            $order->update([
                'status' => OrderStatus::RECEIVED,
                'received_at' => $now,
                'received_by_member_id' => $member?->id,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => OrderStatus::RECEIVED,
                'changed_by_member_id' => $member?->id,
                'changed_at' => $now,
            ]);
        });

        $fresh = $order->fresh();

        return response()->json([
            'order_id' => $order->id,
            'status' => $fresh->status->value,
            'status_label' => $fresh->status->label(),
            'received_at' => $fresh->received_at?->toIso8601String(),
        ]);
    }

    public function reorder(Request $request, Dealer $dealer, Order $order): JsonResponse
    {
        $shop = $this->shop($request);
        $telegramId = (int) ($request->attributes->get('telegram_id') ?? 0);

        if ($shop === null || $telegramId === 0) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        if ($order->shop_id !== $shop->id || $order->dealer_id !== $dealer->id) {
            return response()->json(['message' => 'Buyurtma topilmadi'], 404);
        }

        $result = $this->reorder->execute($shop, $order, $telegramId);

        /** @var Cart $cart */
        $cart = $result['cart'];

        return response()->json([
            'items' => $cart->jsonSerialize(),
            'total' => $cart->total(),
            'count' => $cart->count(),
            'added' => $result['added'],
            'skipped' => $result['skipped'],
        ]);
    }

    private function shop(Request $request): ?Shop
    {
        return $request->attributes->get('shop');
    }

    private function member(Request $request): ?ShopMember
    {
        return $request->attributes->get('member');
    }
}
