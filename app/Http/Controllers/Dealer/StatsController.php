<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\PlatformFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class StatsController extends Controller
{
    private const DAILY_CHART_DAYS = 30;

    private const MONTHLY_REVENUE_MONTHS = 12;

    private const TOP_LIMIT = 5;

    private const LOW_STOCK_THRESHOLD = 10;

    private const DEAD_STOCK_DAYS = 30;

    private const INACTIVE_SHOP_DAYS = 30;

    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly PlatformFinanceService $finance,
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $dealer = Dealer::query()->with('commissionPeriods')->findOrFail($dealerId);

        // KPI va finance — birinchi ekranda darrov ko'rinadi (inline + cache).
        // Qolganlari deferred — sahifa darhol yuklanadi, og'ir bloklar fonda keladi.
        return Inertia::render('Dealer/Stats/Index', [
            'onboarding' => $dealer->needsOnboarding() ? $this->onboarding($dealer) : null,
            'trial' => $dealer->trial_ends_at !== null ? [
                'ends_at' => $dealer->trial_ends_at->toIso8601String(),
                'days_left' => $dealer->trialDaysLeft(),
                'expired' => $dealer->trialExpired(),
            ] : null,
            'kpis' => $this->cached($dealerId, 'kpis', fn () => $this->kpis($dealerId)),
            'finance' => $this->cached($dealerId, 'finance', fn () => $this->finance->snapshot($dealer)),

            'chart' => Inertia::defer(fn () => $this->cached($dealerId, 'chart', fn () => $this->dailyChart($dealerId))),
            'monthlyRevenue' => Inertia::defer(fn () => $this->cached($dealerId, 'monthlyRevenue', fn () => $this->monthlyRevenue($dealerId))),
            'statusBreakdown' => Inertia::defer(fn () => $this->cached($dealerId, 'statusBreakdown', fn () => $this->statusBreakdown($dealerId))),

            'topShops' => Inertia::defer(fn () => $this->cached($dealerId, 'topShops', fn () => $this->topShops($dealerId)), 'lists'),
            'topProducts' => Inertia::defer(fn () => $this->cached($dealerId, 'topProducts', fn () => $this->topProducts($dealerId)), 'lists'),
            'deadStock' => Inertia::defer(fn () => $this->cached($dealerId, 'deadStock', fn () => $this->analytics->deadStock($dealerId)), 'lists'),
            'topDeliverymen' => Inertia::defer(fn () => $this->cached($dealerId, 'topDeliverymen', fn () => $this->analytics->topDeliverymen($dealerId)), 'lists'),
            'inactiveShops' => Inertia::defer(fn () => $this->cached($dealerId, 'inactiveShops', fn () => $this->analytics->inactiveShops($dealerId)), 'lists'),
        ]);
    }

    /**
     * Yangi diller uchun onboarding checklist holati + bildirishnoma ulash deep-link.
     *
     * @return array{dealer_id: int, steps: array<string, bool>, bot_username: string|null, connect_url: string|null}
     */
    private function onboarding(Dealer $dealer): array
    {
        return [
            'dealer_id' => $dealer->id,
            'steps' => [
                'bot_connected' => $dealer->bot_token !== null,
                'notifications_connected' => $dealer->telegram_chat_id !== null,
                'has_category' => ProductCategory::query()->where('dealer_id', $dealer->id)->exists(),
                'has_product' => Product::query()->where('dealer_id', $dealer->id)->exists(),
                'has_shop' => Shop::query()->where('dealer_id', $dealer->id)->exists(),
                'has_deliveryman' => User::query()
                    ->where('dealer_id', $dealer->id)
                    ->where('role', UserRole::DELIVERYMAN)
                    ->exists(),
            ],
            'bot_username' => $dealer->bot_username,
            // Bildirishnoma deep-link faqat bot ulangach (username mavjud) ishlaydi.
            'connect_url' => $dealer->bot_username !== null
                ? 'https://t.me/'.$dealer->bot_username.'?start='.$dealer->ensureOwnerLinkToken()
                : null,
        ];
    }

    private function cached(int $dealerId, string $key, \Closure $resolver): mixed
    {
        return Cache::remember(
            "dealer:{$dealerId}:stats:{$key}",
            self::CACHE_TTL_SECONDS,
            $resolver,
        );
    }

    /** @return array<string, int> */
    private function kpis(int $dealerId): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $todayStart = $now->startOfDay();
        $deadStockThreshold = $now->subDays(self::DEAD_STOCK_DAYS);
        $inactiveShopThreshold = $now->subDays(self::INACTIVE_SHOP_DAYS);

        // Bitta query da barcha buyurtma sanog'i + aylanma + chegirma
        $ordersAgg = Order::query()->forDealer($dealerId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assembling,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivering,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as today_count,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as month_count,
                COALESCE(SUM(CASE WHEN status IN (?, ?) THEN delivered_total ELSE 0 END), 0) as total_gross,
                COALESCE(SUM(CASE WHEN status IN (?, ?) THEN discount ELSE 0 END), 0) as total_discount,
                COALESCE(SUM(CASE WHEN status IN (?, ?) AND created_at >= ? THEN delivered_total ELSE 0 END), 0) as month_gross,
                COALESCE(SUM(CASE WHEN status IN (?, ?) AND created_at >= ? THEN discount ELSE 0 END), 0) as month_discount
            ', [
                OrderStatus::PENDING->value,
                OrderStatus::ASSEMBLING->value,
                OrderStatus::DELIVERING->value,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value,
                $todayStart,
                $monthStart,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value, $monthStart,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value, $monthStart,
            ])
            ->first();

        $shopAgg = Shop::query()->forDealer($dealerId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active,
                COALESCE(SUM(CASE WHEN balance < 0 THEN -balance ELSE 0 END), 0) as debt,
                COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as credit,
                COALESCE(SUM(balance), 0) as net
            ')
            ->first();

        $productAgg = Product::query()->forDealer($dealerId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN stock <= min_stock AND stock > 0 THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out,
                SUM(CASE WHEN stock < 0 THEN 1 ELSE 0 END) as neg
            ')
            ->first();

        $deadStockCount = (int) Product::query()
            ->forDealer($dealerId)
            ->active()
            ->where('stock', '>', 0)
            ->whereNotIn('id', function ($sub) use ($dealerId, $deadStockThreshold) {
                $sub->select('order_items.product_id')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.dealer_id', $dealerId)
                    ->where('orders.status', OrderStatus::DELIVERED->value)
                    ->where('orders.delivered_at', '>=', $deadStockThreshold);
            })
            ->count();

        $inactiveShopsCount = (int) Shop::query()
            ->forDealer($dealerId)
            ->where('is_active', true)
            ->whereNotIn('id', function ($sub) use ($dealerId, $inactiveShopThreshold) {
                $sub->select('shop_id')
                    ->from('orders')
                    ->where('dealer_id', $dealerId)
                    ->where('created_at', '>=', $inactiveShopThreshold);
            })
            ->count();

        $totalGross = (int) ($ordersAgg->total_gross ?? 0);
        $totalDiscount = (int) ($ordersAgg->total_discount ?? 0);
        $monthGross = (int) ($ordersAgg->month_gross ?? 0);
        $monthDiscount = (int) ($ordersAgg->month_discount ?? 0);

        return [
            'total_shops' => (int) ($shopAgg->total ?? 0),
            'active_shops' => (int) ($shopAgg->active ?? 0),
            'total_orders' => (int) ($ordersAgg->total ?? 0),
            'pending_orders' => (int) ($ordersAgg->pending ?? 0),
            'assembling_orders' => (int) ($ordersAgg->assembling ?? 0),
            'delivering_orders' => (int) ($ordersAgg->delivering ?? 0),
            'delivered_orders' => (int) ($ordersAgg->delivered ?? 0),
            'today_orders' => (int) ($ordersAgg->today_count ?? 0),
            'total_products' => (int) ($productAgg->total ?? 0),
            'low_stock_products' => (int) ($productAgg->low ?? 0),
            'out_of_stock_products' => (int) ($productAgg->out ?? 0),
            'negative_stock_products' => (int) ($productAgg->neg ?? 0),
            'dead_stock_count' => $deadStockCount,
            'inactive_shops_count' => $inactiveShopsCount,
            'total_turnover' => $totalGross - $totalDiscount,
            'total_discount' => $totalDiscount,
            'month_turnover' => $monthGross - $monthDiscount,
            'month_discount' => $monthDiscount,
            'month_orders' => (int) ($ordersAgg->month_count ?? 0),
            'shop_debt_total' => (int) ($shopAgg->debt ?? 0),
            'shop_credit_total' => (int) ($shopAgg->credit ?? 0),
            'shop_balance_net' => (int) ($shopAgg->net ?? 0),
        ];
    }

    /** @return list<array{date: string, count: int, total: int}> */
    private function dailyChart(int $dealerId): array
    {
        $from = CarbonImmutable::now()->subDays(self::DAILY_CHART_DAYS - 1)->startOfDay();

        $rows = Order::query()
            ->forDealer($dealerId)
            ->where('created_at', '>=', $from)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(total), 0) as total'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];

        for ($i = 0; $i < self::DAILY_CHART_DAYS; $i++) {
            $date = CarbonImmutable::now()->subDays(self::DAILY_CHART_DAYS - 1 - $i)->format('Y-m-d');
            $row = $rows->get($date);

            $result[] = [
                'date' => $date,
                'count' => (int) ($row?->count ?? 0),
                'total' => (int) ($row?->total ?? 0),
            ];
        }

        return $result;
    }

    /** @return list<array{month: string, revenue: int, discount: int, orders: int}> */
    private function monthlyRevenue(int $dealerId): array
    {
        $from = CarbonImmutable::now()->subMonthsNoOverflow(self::MONTHLY_REVENUE_MONTHS - 1)->startOfMonth();

        // DB-agnostik: kunlik aggregate olamiz va PHP da oylik guruhlaymiz
        $daily = Order::query()
            ->forDealer($dealerId)
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
        for ($i = self::MONTHLY_REVENUE_MONTHS - 1; $i >= 0; $i--) {
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
    }

    /** @return list<array{id: int, name: string, orders: int, revenue: int, discount: int}> */
    private function topShops(int $dealerId): array
    {
        return Shop::query()
            ->forDealer($dealerId)
            ->withCount(['orders' => fn ($q) => $q->fulfilled()])
            ->withSum(['orders as gross_revenue' => fn ($q) => $q->fulfilled()], 'delivered_total')
            ->withSum(['orders as discount_total' => fn ($q) => $q->fulfilled()], 'discount')
            ->orderByDesc('gross_revenue')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (Shop $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'orders' => (int) $s->orders_count,
                'revenue' => (int) ($s->gross_revenue ?? 0) - (int) ($s->discount_total ?? 0),
                'discount' => (int) ($s->discount_total ?? 0),
            ])
            ->all();
    }

    /** @return list<array{product_id: int, name: string, qty: float, revenue: int}> */
    private function topProducts(int $dealerId): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.dealer_id', $dealerId)
            ->whereIn('orders.status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->select([
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as name'),
                DB::raw('SUM(order_items.qty) as qty'),
                DB::raw('SUM(order_items.price * order_items.qty) as revenue'),
            ])
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'qty' => (float) $row->qty,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /** @return array<string, int> */
    private function statusBreakdown(int $dealerId): array
    {
        // DB::table bypassing Eloquent casts — status string kalit bo'lib qoladi (enum bo'lmaydi)
        $rows = DB::table('orders')
            ->where('dealer_id', $dealerId)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $result = [];
        foreach (OrderStatus::cases() as $s) {
            $result[$s->value] = (int) ($rows[$s->value] ?? 0);
        }

        return $result;
    }
}
