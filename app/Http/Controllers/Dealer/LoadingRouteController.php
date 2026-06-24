<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\RoutingException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Routing\Coordinate;
use App\Services\Routing\RouteOptimizer;
use App\Services\Routing\RouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyurtmalarni real yo'l masofasi bo'yicha optimallashtirilgan
 * marshrutga aylantiradi. Yetkazib berish tartibi (yaqindan uzoqqa) va
 * mashinaga yuklash tartibi (LIFO: uzoqdagi 1-yuklanadi) qaytariladi.
 */
final class LoadingRouteController extends Controller
{
    private const int MAX_STOPS = 50;

    public function __construct(private readonly RouteOptimizer $optimizer) {}

    /**
     * POST /dealer/orders/loading-route
     * Body: { order_ids: int[] }.
     */
    public function compute(Request $request): JsonResponse
    {
        $user = $request->user();
        $dealer = $user->dealer;

        if ($dealer === null) {
            return response()->json(['message' => 'Diller topilmadi'], 403);
        }

        if ($dealer->warehouse_latitude === null || $dealer->warehouse_longitude === null) {
            throw RoutingException::warehouseNotConfigured();
        }

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_STOPS],
            'order_ids.*' => ['integer'],
        ]);

        $orders = Order::query()
            ->forDealer((int) $dealer->id)
            ->shopChannel()
            ->with('shop:id,name,address,phone,latitude,longitude,region,district')
            ->whereIn('id', $validated['order_ids'])
            ->whereIn('status', [
                OrderStatus::PENDING,
                OrderStatus::ASSEMBLING,
                OrderStatus::DELIVERING,
            ])
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Yetkazib berish kerak buyurtmalar topilmadi'], 422);
        }

        $stops = [];
        $skipped = [];

        foreach ($orders as $order) {
            $shop = $order->shop;

            if ($shop === null || $shop->latitude === null || $shop->longitude === null) {
                $skipped[] = [
                    'order_id' => $order->id,
                    'shop_name' => $shop?->name,
                    'reason' => 'no_coordinates',
                ];

                continue;
            }

            $stops[] = new RouteStop(
                coordinate: new Coordinate((float) $shop->latitude, (float) $shop->longitude),
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->displayNumber(),
                    'order_total' => (int) $order->total,
                    'order_status' => $order->status->value,
                    'order_status_label' => $order->status->label(),
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'shop_phone' => $shop->phone,
                    'shop_address' => $shop->address,
                    'shop_region' => $shop->region,
                    'shop_district' => $shop->district,
                    'shop_latitude' => (float) $shop->latitude,
                    'shop_longitude' => (float) $shop->longitude,
                ],
            );
        }

        if ($stops === []) {
            return response()->json([
                'message' => 'Buyurtmalardagi do\'konlarda koordinata yo\'q',
                'skipped' => $skipped,
            ], 422);
        }

        $warehouse = new RouteStop(
            coordinate: new Coordinate(
                (float) $dealer->warehouse_latitude,
                (float) $dealer->warehouse_longitude,
            ),
            payload: [
                'name' => 'Ombor',
                'address' => $dealer->warehouse_address,
                'latitude' => (float) $dealer->warehouse_latitude,
                'longitude' => (float) $dealer->warehouse_longitude,
            ],
        );

        $route = $this->optimizer->optimize($warehouse, $stops, self::MAX_STOPS);

        return response()->json([
            'warehouse' => $warehouse->payload,
            'delivery_sequence' => $route->sequence,
            'loading_sequence' => $route->loadingOrder(),
            'total_distance_meters' => $route->totalDistanceMeters,
            'total_duration_seconds' => $route->totalDurationSeconds,
            'return_distance_meters' => $route->returnToWarehouseDistanceMeters,
            'return_duration_seconds' => $route->returnToWarehouseDurationSeconds,
            'skipped' => $skipped,
        ]);
    }

    /**
     * GET /dealer/orders/loading-route?order_ids[]=... — alohida sahifa.
     */
    public function show(Request $request): Response
    {
        $dealer = $request->user()->dealer;
        $warehouseConfigured = $dealer?->warehouse_latitude !== null
            && $dealer?->warehouse_longitude !== null;

        $orderIds = array_values(array_filter(
            (array) $request->input('order_ids', []),
            fn ($v): bool => $v !== null && $v !== '' && is_numeric($v),
        ));
        $orderIds = array_map('intval', $orderIds);

        return Inertia::render('Dealer/Orders/LoadingRoute', [
            'warehouseConfigured' => $warehouseConfigured,
            'warehouse' => $warehouseConfigured ? [
                'latitude' => (float) $dealer->warehouse_latitude,
                'longitude' => (float) $dealer->warehouse_longitude,
                'address' => $dealer->warehouse_address,
            ] : null,
            'orderIds' => $orderIds,
            'maxStops' => self::MAX_STOPS,
        ]);
    }
}
