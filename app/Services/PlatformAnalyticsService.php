<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dealer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;

/**
 * Platforma darajasidagi analitika (super admin).
 * Og'ir agregatlar 5 daqiqa cache lanadi.
 */
final class PlatformAnalyticsService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Oxirgi 12 oy aylanmasi (DELIVERED buyurtmalar) — chegirma ayirilgan holda.
     *
     * @return list<array{month: string, revenue: int, discount: int, orders: int}>
     */
    public function monthlyRevenue(): array
    {
        return $this->cached('monthly_revenue', function (): array {
            $from = CarbonImmutable::now()->subMonthsNoOverflow(11)->startOfMonth();

            $daily = Order::query()
                ->fulfilled()
                ->where('created_at', '>=', $from)
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('COALESCE(SUM(delivered_total), 0) as gross'),
                    DB::raw('COALESCE(SUM(discount), 0) as discount'),
                ])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->get();

            $byMonth = [];
            foreach ($daily as $row) {
                $month = substr((string) $row->date, 0, 7);
                $byMonth[$month] ??= ['orders' => 0, 'gross' => 0, 'discount' => 0];
                $byMonth[$month]['orders'] += (int) $row->orders;
                $byMonth[$month]['gross'] += (int) $row->gross;
                $byMonth[$month]['discount'] += (int) $row->discount;
            }

            $result = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = CarbonImmutable::now()->subMonthsNoOverflow($i)->format('Y-m');
                $bucket = $byMonth[$month] ?? ['orders' => 0, 'gross' => 0, 'discount' => 0];
                $result[] = [
                    'month' => $month,
                    'revenue' => $bucket['gross'] - $bucket['discount'],
                    'discount' => $bucket['discount'],
                    'orders' => $bucket['orders'],
                ];
            }

            return $result;
        });
    }

    /**
     * Oy-bo'yicha o'sish: dealerlar, mijozlar, buyurtmalar.
     *
     * @return array{dealers: array{current: int, previous: int, delta_pct: float}, shops: array{current: int, previous: int, delta_pct: float}, orders: array{current: int, previous: int, delta_pct: float}}
     */
    public function growth(): array
    {
        return $this->cached('growth', function (): array {
            $now = CarbonImmutable::now();
            $thisMonth = $now->startOfMonth();
            $prevMonth = $thisMonth->subMonthNoOverflow();

            // Har jadval bo'yicha bitta query — joriy va oldingi oy bir aggregate ichida
            $aggregate = function (string $table) use ($prevMonth, $thisMonth, $now): array {
                $row = DB::table($table)
                    ->where('created_at', '>=', $prevMonth)
                    ->where('created_at', '<', $now)
                    ->selectRaw('
                        SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as current,
                        SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as previous
                    ', [$thisMonth, $now, $prevMonth, $thisMonth])
                    ->first();

                return [(int) ($row->current ?? 0), (int) ($row->previous ?? 0)];
            };

            [$dealersCur, $dealersPrev] = $aggregate('dealers');
            [$shopsCur, $shopsPrev] = $aggregate('shops');
            [$ordersCur, $ordersPrev] = $aggregate('orders');

            return [
                'dealers' => $this->pack($dealersCur, $dealersPrev),
                'shops' => $this->pack($shopsCur, $shopsPrev),
                'orders' => $this->pack($ordersCur, $ordersPrev),
            ];
        });
    }

    /**
     * Dillerlar aktivligi: so'nggi zakas, 30 kun zakas soni, holat.
     *
     * @return list<array{id: int, name: string, is_active: bool, has_webhook: bool, shops: int, orders_30d: int, last_order_at: string|null, days_since: int|null, status: string}>
     */
    public function dealerActivity(): array
    {
        return $this->cached('dealer_activity', function (): array {
            $threshold = CarbonImmutable::now()->subDays(30);

            $lastOrder = DB::table('orders')
                ->select('dealer_id', DB::raw('MAX(created_at) as last_order_at'))
                ->groupBy('dealer_id');

            $recentOrders = DB::table('orders')
                ->where('created_at', '>=', $threshold)
                ->select('dealer_id', DB::raw('COUNT(*) as orders_30d'))
                ->groupBy('dealer_id');

            $shopCounts = DB::table('shops')
                ->select('dealer_id', DB::raw('COUNT(*) as shops_count'))
                ->groupBy('dealer_id');

            return Dealer::query()
                ->leftJoinSub($lastOrder, 'lo', 'lo.dealer_id', '=', 'dealers.id')
                ->leftJoinSub($recentOrders, 'ro', 'ro.dealer_id', '=', 'dealers.id')
                ->leftJoinSub($shopCounts, 'sc', 'sc.dealer_id', '=', 'dealers.id')
                ->orderByRaw('COALESCE(ro.orders_30d, 0) DESC')
                ->orderBy('dealers.name')
                ->get([
                    'dealers.id',
                    'dealers.name',
                    'dealers.is_active',
                    'dealers.webhook_set_at',
                    DB::raw('lo.last_order_at as last_order_at'),
                    DB::raw('ro.orders_30d as orders_30d'),
                    DB::raw('sc.shops_count as shops_count'),
                ])
                ->map(function ($d): array {
                    $lastAt = $d->last_order_at !== null ? (string) $d->last_order_at : null;
                    $days = $lastAt !== null
                        ? max(0, (int) CarbonImmutable::parse($lastAt)->diffInDays(CarbonImmutable::now()))
                        : null;

                    return [
                        'id' => (int) $d->id,
                        'name' => (string) $d->name,
                        'is_active' => (bool) $d->is_active,
                        'has_webhook' => $d->webhook_set_at !== null,
                        'shops' => (int) ($d->shops_count ?? 0),
                        'orders_30d' => (int) ($d->orders_30d ?? 0),
                        'last_order_at' => $lastAt,
                        'days_since' => $days,
                        'status' => $this->statusFor((bool) $d->is_active, $days, (int) ($d->orders_30d ?? 0)),
                    ];
                })
                ->all();
        });
    }

    /**
     * @return list<array{id: int, name: string, last_order_at: string|null, days_since: int|null, shops: int}>
     */
    public function inactiveDealers(int $days = 14): array
    {
        return $this->cached("inactive_dealers:{$days}", function () use ($days): array {
            $threshold = CarbonImmutable::now()->subDays($days);

            $lastOrder = DB::table('orders')
                ->select('dealer_id', DB::raw('MAX(created_at) as last_order_at'))
                ->groupBy('dealer_id');

            $shopCounts = DB::table('shops')
                ->select('dealer_id', DB::raw('COUNT(*) as shops_count'))
                ->groupBy('dealer_id');

            return Dealer::query()
                ->active()
                ->leftJoinSub($lastOrder, 'lo', 'lo.dealer_id', '=', 'dealers.id')
                ->leftJoinSub($shopCounts, 'sc', 'sc.dealer_id', '=', 'dealers.id')
                ->where(function ($q) use ($threshold): void {
                    $q->whereNull('lo.last_order_at')
                        ->orWhere('lo.last_order_at', '<', $threshold);
                })
                ->orderByRaw('lo.last_order_at IS NULL DESC')
                ->orderBy('lo.last_order_at')
                ->limit(20)
                ->get([
                    'dealers.id',
                    'dealers.name',
                    DB::raw('lo.last_order_at as last_order_at'),
                    DB::raw('sc.shops_count as shops_count'),
                ])
                ->map(function ($d): array {
                    $lastAt = $d->last_order_at !== null ? (string) $d->last_order_at : null;
                    $daysSince = $lastAt !== null
                        ? max(0, (int) CarbonImmutable::parse($lastAt)->diffInDays(CarbonImmutable::now()))
                        : null;

                    return [
                        'id' => (int) $d->id,
                        'name' => (string) $d->name,
                        'last_order_at' => $lastAt,
                        'days_since' => $daysSince,
                        'shops' => (int) ($d->shops_count ?? 0),
                    ];
                })
                ->all();
        });
    }

    public function invalidate(): void
    {
        foreach (['monthly_revenue', 'growth', 'dealer_activity', 'inactive_dealers:14'] as $key) {
            $this->cache->forget("platform_analytics:{$key}");
        }
    }

    /**
     * @return array{current: int, previous: int, delta_pct: float}
     */
    private function pack(int $current, int $previous): array
    {
        $delta = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100.0 : 0.0);

        return [
            'current' => $current,
            'previous' => $previous,
            'delta_pct' => round($delta, 1),
        ];
    }

    private function statusFor(bool $isActive, ?int $daysSince, int $orders30d): string
    {
        if (! $isActive) {
            return 'disabled';
        }

        if ($daysSince === null) {
            return 'new';
        }

        if ($daysSince > 30) {
            return 'dormant';
        }

        if ($orders30d >= 10) {
            return 'thriving';
        }

        return 'active';
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
            "platform_analytics:{$key}",
            self::CACHE_TTL_SECONDS,
            $callback,
        );
    }
}
