<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;

/**
 * Diller analitikasi — og'ir agregatlar 5-10 daqiqa cache lanadi.
 * Buyurtma yaratilishi/yetkazilishida cache to'g'ridan-to'g'ri invalidate qilinmaydi —
 * qisqa TTL natija real-vaqt yaqinligini ta'minlaydi va DB ga bosim kamayadi.
 */
final class AnalyticsService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * N-kun ichida sotilmagan yoki umuman sotilmagan faol mahsulotlar.
     *
     * @return list<array{id: int, name: string, stock: float, last_sold_at: string|null, days_since: int|null}>
     */
    public function deadStock(int $dealerId, int $days = 30, int $limit = 20): array
    {
        return $this->cached("dead_stock:{$dealerId}:{$days}:{$limit}", function () use ($dealerId, $days, $limit): array {
            $threshold = CarbonImmutable::now()->subDays($days);

            $lastSold = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.dealer_id', $dealerId)
                ->where('orders.channel', '!=', 'marketplace')
                ->where('orders.status', OrderStatus::DELIVERED->value)
                ->select('order_items.product_id', DB::raw('MAX(orders.delivered_at) as last_sold_at'))
                ->groupBy('order_items.product_id');

            return Product::query()
                ->forDealer($dealerId)
                ->active()
                ->leftJoinSub($lastSold, 'ls', 'ls.product_id', '=', 'products.id')
                ->where(function ($q) use ($threshold): void {
                    $q->whereNull('ls.last_sold_at')
                        ->orWhere('ls.last_sold_at', '<', $threshold);
                })
                ->where('products.stock', '>', 0)
                ->orderByRaw('ls.last_sold_at IS NULL DESC')
                ->orderBy('ls.last_sold_at')
                ->limit($limit)
                ->get([
                    'products.id', 'products.name', 'products.stock',
                    DB::raw('ls.last_sold_at as last_sold_at'),
                ])
                ->map(function ($p): array {
                    $lastSoldAt = $p->last_sold_at !== null ? (string) $p->last_sold_at : null;
                    $daysSince = $lastSoldAt !== null
                        ? max(0, (int) CarbonImmutable::parse($lastSoldAt)->diffInDays(CarbonImmutable::now()))
                        : null;

                    return [
                        'id' => (int) $p->id,
                        'name' => (string) $p->name,
                        'stock' => (float) $p->stock,
                        'last_sold_at' => $lastSoldAt,
                        'days_since' => $daysSince,
                    ];
                })
                ->all();
        });
    }

    /**
     * Yetkazib beruvchilar bo'yicha samaradorlik.
     *
     * @return list<array{id: int, name: string, orders: int, delivered: int, revenue: int}>
     */
    public function topDeliverymen(int $dealerId, int $limit = 10): array
    {
        return $this->cached("top_deliverymen:{$dealerId}:{$limit}", function () use ($dealerId, $limit): array {
            $deliverymen = User::query()
                ->where('dealer_id', $dealerId)
                ->where('role', UserRole::DELIVERYMAN)
                ->pluck('id', 'id');

            if ($deliverymen->isEmpty()) {
                return [];
            }

            $deliveredCondition = "orders.status = '".OrderStatus::DELIVERED->value."'";
            $rows = DB::table('orders')
                ->join('shops', 'shops.id', '=', 'orders.shop_id')
                ->whereIn('shops.deliveryman_id', $deliverymen->keys())
                ->where('orders.dealer_id', $dealerId)
                ->where('orders.channel', '!=', 'marketplace')
                ->select([
                    'shops.deliveryman_id',
                    DB::raw('COUNT(orders.id) as orders'),
                    DB::raw("SUM(CASE WHEN {$deliveredCondition} THEN 1 ELSE 0 END) as delivered"),
                    DB::raw("SUM(CASE WHEN {$deliveredCondition} THEN orders.total - COALESCE(orders.discount, 0) ELSE 0 END) as revenue"),
                ])
                ->groupBy('shops.deliveryman_id')
                ->get()
                ->keyBy('deliveryman_id');

            return User::query()
                ->whereIn('id', $deliverymen->keys())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function (User $u) use ($rows): array {
                    $row = $rows->get($u->id);

                    return [
                        'id' => $u->id,
                        'name' => (string) $u->name,
                        'orders' => (int) ($row->orders ?? 0),
                        'delivered' => (int) ($row->delivered ?? 0),
                        'revenue' => (int) ($row->revenue ?? 0),
                    ];
                })
                ->sortByDesc('revenue')
                ->take($limit)
                ->values()
                ->all();
        });
    }

    /**
     * N kundan ko'p vaqt davomida buyurtma bermagan mijozlar.
     *
     * @return list<array{id: int, name: string, last_order_at: string|null, days_since: int|null, balance: int}>
     */
    public function inactiveShops(int $dealerId, int $days = 30, int $limit = 20): array
    {
        return $this->cached("inactive_shops:{$dealerId}:{$days}:{$limit}", function () use ($dealerId, $days, $limit): array {
            $threshold = CarbonImmutable::now()->subDays($days);

            $lastOrder = DB::table('orders')
                ->where('dealer_id', $dealerId)
                ->where('channel', '!=', 'marketplace')
                ->select('shop_id', DB::raw('MAX(created_at) as last_order_at'))
                ->groupBy('shop_id');

            return Shop::query()
                ->forDealer($dealerId)
                ->active()
                ->leftJoinSub($lastOrder, 'lo', 'lo.shop_id', '=', 'shops.id')
                ->where(function ($q) use ($threshold): void {
                    $q->whereNull('lo.last_order_at')
                        ->orWhere('lo.last_order_at', '<', $threshold);
                })
                ->orderByRaw('lo.last_order_at IS NULL DESC')
                ->orderBy('lo.last_order_at')
                ->limit($limit)
                ->get([
                    'shops.id', 'shops.name', 'shops.balance',
                    DB::raw('lo.last_order_at as last_order_at'),
                ])
                ->map(function ($s): array {
                    $lastOrderAt = $s->last_order_at !== null ? (string) $s->last_order_at : null;
                    $daysSince = $lastOrderAt !== null
                        ? max(0, (int) CarbonImmutable::parse($lastOrderAt)->diffInDays(CarbonImmutable::now()))
                        : null;

                    return [
                        'id' => (int) $s->id,
                        'name' => (string) $s->name,
                        'last_order_at' => $lastOrderAt,
                        'days_since' => $daysSince,
                        'balance' => (int) $s->balance,
                    ];
                })
                ->all();
        });
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function cached(string $key, \Closure $callback): mixed
    {
        return $this->cache->remember(
            "analytics:{$key}",
            self::CACHE_TTL_SECONDS,
            $callback,
        );
    }
}
