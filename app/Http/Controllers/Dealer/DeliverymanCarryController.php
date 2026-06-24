<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class DeliverymanCarryController extends Controller
{
    /**
     * Yetkazib beruvchilar qo'lidagi qoldiq (carry): DELIVERING statusidagi
     * barcha buyurtmalar + DELIVERED/RECEIVED statusda picked > delivered + returned
     * bo'lgan vozvrat kutilayotgan buyurtmalar. Sklad va dillerga ko'rinadi,
     * yetkazib beruvchi esa faqat o'zining qoldig'ini ko'radi.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isWarehouse() || $user->isDeliveryman(), 403);

        $dealerId = (int) $user->dealer_id;

        $orders = Order::query()
            ->forDealer($dealerId)
            ->whereIn('status', [OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED])
            ->whereNotNull('deliveryman_id')
            ->when($user->isDeliveryman(), fn ($q) => $q->where('deliveryman_id', $user->id))
            ->with(['shop', 'deliveryman:id,name,phone', 'items'])
            ->orderByDesc('delivering_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Order $o): bool => $o->items->sum(fn ($i) => $i->carryQty()) > 0)
            ->values();

        // Yetkazib beruvchilar bo'yicha guruhlash
        $grouped = $orders
            ->groupBy('deliveryman_id')
            ->map(function ($ordersForDriver, $driverId) {
                /** @var Collection<int, Order> $ordersForDriver */
                $deliveryman = $ordersForDriver->first()?->deliveryman;
                $carryTotal = (int) $ordersForDriver->sum(fn (Order $o) => $o->items->sum(fn ($i) => $i->carrySubtotal()));
                $itemCount = (int) $ordersForDriver->sum(fn (Order $o) => $o->items->filter(fn ($i) => $i->carryQty() > 0)->count());

                return [
                    'deliveryman' => [
                        'id' => (int) $driverId,
                        'name' => $deliveryman?->name,
                        'phone' => $deliveryman?->phone,
                    ],
                    'carry_total' => $carryTotal,
                    'orders_count' => $ordersForDriver->count(),
                    'items_count' => $itemCount,
                    'orders' => OrderResource::collection($ordersForDriver),
                ];
            })
            ->values();

        return Inertia::render('Dealer/Carry/Index', [
            'groups' => $grouped,
            'summary' => [
                'orders' => $orders->count(),
                'carry_total' => (int) $orders->sum(fn (Order $o) => $o->items->sum(fn ($i) => $i->carrySubtotal())),
                'deliverymen_count' => $grouped->count(),
            ],
        ]);
    }
}
