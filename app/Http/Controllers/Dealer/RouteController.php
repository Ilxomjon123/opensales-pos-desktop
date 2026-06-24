<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Routing\Coordinate;
use App\Services\Routing\RouteOptimizer;
use App\Services\Routing\RouteStop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class RouteController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly RouteOptimizer $optimizer,
    ) {}

    /**
     * Bugungi marshrut — yetkazib beruvchi uchun o'ziga biriktirilgan,
     * owner/skladchi uchun tanlangan yetkazib beruvchining (yoki barchaning)
     * bugun yaratilgan buyurtmalari + statistikalar.
     */
    public function today(Request $request): Response
    {
        $user = $request->user();
        $dealerId = (int) $user->dealer_id;
        $date = $this->resolveDate($request);
        $deliverymanId = $this->resolveDeliverymanId($request);

        $deliverymen = $user->isOwner() || $user->isWarehouse()
            ? User::query()
                ->where('dealer_id', $dealerId)
                ->where('role', UserRole::DELIVERYMAN)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $orders = Order::query()
            ->forDealer($dealerId)
            ->with(['shop:id,name,address,phone,latitude,longitude,region,district', 'items:id,order_id,price,pack_price,pack_size,qty,pack_qty,picked_qty,picked_pack_qty'])
            ->withPendingReturn()
            ->when($deliverymanId !== null, fn ($q) => $q->where('deliveryman_id', $deliverymanId))
            ->where(function ($q) use ($date): void {
                $dateStr = $date->toDateString();

                // Hali yo'lda — pending/assembling/delivering, $date dan oldin (yoki shu kunda) yaratilgan.
                $q->where(function ($qq) use ($dateStr): void {
                    $qq->whereDate('created_at', '<=', $dateStr)
                        ->whereIn('status', [
                            OrderStatus::PENDING,
                            OrderStatus::ASSEMBLING,
                            OrderStatus::DELIVERING,
                        ]);
                })
                    // Shu sanada yetkazildi (status delivered, lekin hali qabul qilinmagan).
                    ->orWhere(function ($qq) use ($dateStr): void {
                        $qq->where('status', OrderStatus::DELIVERED)
                            ->whereDate('delivered_at', $dateStr);
                    })
                    // Shu sanada qabul qilindi.
                    ->orWhereDate('received_at', $dateStr)
                    // Shu sanada bekor qilindi.
                    ->orWhereDate('cancelled_at', $dateStr);
            })
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        $deliveryPositions = $this->computeDeliveryPositions($orders, $user->dealer);

        return Inertia::render('Dealer/Routes/Today', [
            'orders' => $this->serializeOrders($orders, $deliveryPositions),
            'stats' => $this->buildStats($orders, $date),
            'date' => $date->toDateString(),
            'deliverymen' => $deliverymen->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name]),
            'selectedDeliverymanId' => $deliverymanId,
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<int, int>  $deliveryPositions  orderId => yetkazib berish tartibi
     * @return list<array<string, mixed>>
     */
    private function serializeOrders(Collection $orders, array $deliveryPositions = []): array
    {
        return $orders->map(function (Order $o) use ($deliveryPositions): array {
            $shop = $o->shop;

            return [
                'id' => $o->id,
                'number' => $o->displayNumber(),
                // Ombordan optimal yetkazib berish tartibi (null = marshrutda emas).
                'delivery_position' => $deliveryPositions[$o->id] ?? null,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total' => $o->total,
                'delivered_total' => $o->delivered_total,
                // ASSEMBLING/DELIVERING'da picked_qty asosida hisoblangan jami;
                // frontend displayTotal'da shu maydon ishlatiladi.
                'prepared_total' => $o->preparedTotal(),
                'display_total' => $o->displayTotal(),
                'discount' => (int) ($o->discount ?? 0),
                'items_count' => $o->items->count(),
                'note' => $o->note,
                'created_at' => $o->created_at?->toIso8601String(),
                'delivering_at' => $o->delivering_at?->toIso8601String(),
                'delivered_at' => $o->delivered_at?->toIso8601String(),
                'received_at' => $o->received_at?->toIso8601String(),
                'has_pending_return' => in_array($o->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)
                    && (bool) ($o->has_pending_return ?? false),
                'shop' => $shop !== null ? [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'region' => $shop->region,
                    'district' => $shop->district,
                    'phone' => $shop->phone,
                    'latitude' => $shop->latitude,
                    'longitude' => $shop->longitude,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Bugungi yetkazib berish tartibini ombordan optimal hisoblaydi
     * (xuddi "Mashinaga yuklash tartibi" modalidagi kabi). Faqat hali
     * yo'ldagi (pending/assembling/delivering) va koordinatasi bor
     * buyurtmalar tartiblanadi. Ombor sozlanmagan yoki masofa xizmati
     * ishlamasa — bo'sh massiv qaytadi (kartada mijoz tomonidagi
     * nearest-neighbor ishlaydi).
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, int> orderId => delivery_position
     */
    private function computeDeliveryPositions(Collection $orders, ?Dealer $dealer): array
    {
        if ($dealer === null || $dealer->warehouse_latitude === null || $dealer->warehouse_longitude === null) {
            return [];
        }

        $deliverable = $orders->filter(
            fn (Order $o): bool => in_array($o->status, [
                OrderStatus::PENDING,
                OrderStatus::ASSEMBLING,
                OrderStatus::DELIVERING,
            ], true)
                && $o->shop?->latitude !== null
                && $o->shop?->longitude !== null,
        )->values();

        if ($deliverable->isEmpty()) {
            return [];
        }

        if ($deliverable->count() === 1) {
            return [(int) $deliverable->first()->id => 1];
        }

        $stops = $deliverable->map(fn (Order $o): RouteStop => new RouteStop(
            coordinate: new Coordinate((float) $o->shop->latitude, (float) $o->shop->longitude),
            payload: ['order_id' => $o->id],
        ))->all();

        $warehouse = new RouteStop(
            coordinate: new Coordinate(
                (float) $dealer->warehouse_latitude,
                (float) $dealer->warehouse_longitude,
            ),
            payload: ['name' => 'Ombor'],
        );

        try {
            $route = $this->optimizer->optimize($warehouse, $stops);
        } catch (Throwable) {
            return [];
        }

        $positions = [];

        foreach ($route->sequence as $step) {
            $positions[(int) $step['payload']['order_id']] = (int) $step['delivery_position'];
        }

        return $positions;
    }

    /**
     * Bugungi reja, bajarilgan va qolgan qism statistikasi.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, int>
     */
    private function buildStats(Collection $orders, CarbonImmutable $date): array
    {
        $dateStr = $date->toDateString();

        $total = $orders->count();
        $totalAmount = (int) $orders->sum('total');

        // "Bajarildi" — shu kunda yopilgan: received_at yoki delivered_at $date ga to'g'ri kelsa.
        $completedToday = $orders->filter(function (Order $o) use ($dateStr): bool {
            if ($o->status === OrderStatus::RECEIVED) {
                return $o->received_at?->toDateString() === $dateStr;
            }

            if ($o->status === OrderStatus::DELIVERED) {
                return $o->delivered_at?->toDateString() === $dateStr;
            }

            return false;
        });

        $completed = $completedToday->count();
        $completedAmount = (int) $completedToday->sum('total');

        $remaining = $orders->whereIn('status', [
            OrderStatus::PENDING,
            OrderStatus::ASSEMBLING,
            OrderStatus::DELIVERING,
        ])->count();

        $cancelled = $orders->where('status', OrderStatus::CANCELLED)->count();

        $pendingReturn = $orders->filter(
            fn (Order $o) => in_array($o->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)
                && (bool) ($o->has_pending_return ?? false),
        )->count();

        return [
            'total' => $total,
            'total_amount' => $totalAmount,
            'completed' => $completed,
            'completed_amount' => $completedAmount,
            'remaining' => $remaining,
            'cancelled' => $cancelled,
            'pending_return' => $pendingReturn,
        ];
    }

    /**
     * Yetkazib beruvchi "Yo'lga chiqish" bossa — tanlangan ASSEMBLING
     * (Tayyorlandi) buyurtmalari DELIVERING ga ko'chiriladi. Faqat o'ziga
     * biriktirilgan buyurtmalar dispatch qilinadi.
     */
    public function startRoute(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isDeliveryman(), 403);

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ]);

        $orders = Order::query()
            ->forDealer((int) $user->dealer_id)
            ->where('deliveryman_id', $user->id)
            ->where('status', OrderStatus::ASSEMBLING)
            ->whereIn('id', $validated['order_ids'])
            ->get();

        foreach ($orders as $order) {
            $this->orderService->dispatch(order: $order, by: $user);
        }

        return back()->with('status', "{$orders->count()} ta buyurtma yo'lga chiqarildi");
    }

    private function resolveDate(Request $request): CarbonImmutable
    {
        $raw = $request->date('date', 'Y-m-d');

        return $raw !== null ? CarbonImmutable::parse($raw) : CarbonImmutable::today();
    }

    /**
     * Yetkazib beruvchi uchun — har doim o'z id'si.
     * Owner/skladchi uchun — query parametr bo'yicha (null = barchasi).
     */
    private function resolveDeliverymanId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isDeliveryman()) {
            return (int) $user->id;
        }

        $id = $request->integer('deliveryman_id');

        return $id > 0 ? $id : null;
    }
}
