<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Resources\DealerResource;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformPayment;
use App\Models\Shop;
use App\Services\PlatformAnalyticsService;
use App\Services\PlatformFinanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class StatsController extends Controller
{
    private const DAILY_CHART_DAYS = 30;

    private const TOP_DEALERS_LIMIT = 5;

    private const RECENT_PAYMENTS_LIMIT = 15;

    public function __construct(
        private readonly PlatformFinanceService $finance,
        private readonly PlatformAnalyticsService $analytics,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Stats/Index', [
            'totals' => $this->totals(),
            'chart' => $this->dailyChart(),
            'topDealers' => $this->topDealers(),
            'finance' => [
                'totals' => $this->finance->totals(),
                'totals_by_currency' => $this->finance->totalsByCurrency(),
                'dealers' => $this->dealerFinance(),
                'recent_payments' => $this->recentPayments(),
            ],
            'monthlyRevenue' => $this->analytics->monthlyRevenue(),
            'growth' => $this->analytics->growth(),
            'dealerActivity' => $this->analytics->dealerActivity(),
            'inactiveDealers' => $this->analytics->inactiveDealers(),
        ]);
    }

    /** @return array<string, mixed> */
    private function totals(): array
    {
        $agg = Order::query()->fulfilled()
            ->selectRaw('COALESCE(SUM(delivered_total), 0) as gross, COALESCE(SUM(discount), 0) as discount')
            ->first();

        $gross = (int) ($agg->gross ?? 0);
        $discount = (int) ($agg->discount ?? 0);

        return [
            'dealers' => Dealer::query()->count(),
            'active_dealers' => Dealer::query()->active()->count(),
            'shops' => Shop::query()->count(),
            'orders' => Order::query()->count(),
            'pending_orders' => Order::query()->pending()->count(),
            'revenue' => $gross - $discount,
            'discount' => $discount,
            'total_payments' => (int) Payment::query()->sum('amount'),
            // Aralash valyuta — har valyuta bo'yicha alohida (UZS/RUB). Bitta valyuta
            // bo'lsa frontend avvalgidek bitta ko'rsatadi.
            'by_currency' => $this->revenueByCurrency(),
        ];
    }

    /**
     * Aylanma/chegirma/to'lovlar valyuta bo'yicha guruhlangan.
     *
     * @return list<array{currency: string, symbol: string, revenue: int, discount: int, payments: int}>
     */
    private function revenueByCurrency(): array
    {
        $orders = Order::query()->fulfilled()
            ->selectRaw('currency, COALESCE(SUM(delivered_total), 0) as gross, COALESCE(SUM(discount), 0) as discount')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $payments = Payment::query()
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $codes = $orders->keys()->merge($payments->keys())->unique()->filter()->values();

        return $codes->map(function (string $code) use ($orders, $payments): array {
            $currency = Currency::tryFrom($code) ?? Currency::UZS;
            $gross = (int) ($orders->get($code)->gross ?? 0);
            $discount = (int) ($orders->get($code)->discount ?? 0);

            return [
                'currency' => $code,
                'symbol' => $currency->symbol(),
                'revenue' => $gross - $discount,
                'discount' => $discount,
                'payments' => (int) ($payments->get($code)->amount ?? 0),
            ];
        })->all();
    }

    /**
     * So'nggi 30 kunlik kunlik buyurtma statistikasi.
     *
     * @return list<array{date: string, count: int, total: int}>
     */
    private function dailyChart(): array
    {
        $from = Carbon::now()->subDays(self::DAILY_CHART_DAYS - 1)->startOfDay();

        $rows = Order::query()
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
            $date = Carbon::now()->subDays(self::DAILY_CHART_DAYS - 1 - $i)->format('Y-m-d');
            $row = $rows->get($date);

            $result[] = [
                'date' => $date,
                'count' => (int) ($row?->count ?? 0),
                'total' => (int) ($row?->total ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Top-5 diller — buyurtma soni va aylanma bo'yicha.
     */
    private function topDealers(): array
    {
        $dealers = Dealer::query()
            ->withCount(['orders', 'shops'])
            ->withSum(['orders as revenue' => fn ($q) => $q->fulfilled()], 'delivered_total')
            ->withSum(['orders as discount_total' => fn ($q) => $q->fulfilled()], 'discount')
            ->orderByDesc('orders_count')
            ->limit(self::TOP_DEALERS_LIMIT)
            ->get();

        return DealerResource::collection($dealers)->resolve();
    }

    /**
     * Har diller bo'yicha moliya: aylanma, komissiya tipi, qarz/haq va to'lovlar.
     *
     * @return list<array{
     *     id: int, name: string, is_active: bool,
     *     turnover: int, fee_rate: float, fee_owed: int, total_paid: int, balance: int,
     *     commission_type: string, fixed_commission_amount: int|null
     * }>
     */
    private function dealerFinance(): array
    {
        return Dealer::query()
            ->with('commissionPeriods')
            ->withCount('shops')
            ->orderBy('name')
            ->get()
            ->map(function (Dealer $d): array {
                $snap = $this->finance->snapshot($d);

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'is_active' => (bool) $d->is_active,
                    'shops_count' => (int) $d->shops_count,
                    ...$snap,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{id: int, dealer_id: int, dealer_name: string, amount: int, discount: int, note: string|null, created_at: string}> */
    private function recentPayments(): array
    {
        return PlatformPayment::query()
            ->with('dealer:id,name')
            ->latest()
            ->limit(self::RECENT_PAYMENTS_LIMIT)
            ->get()
            ->map(fn (PlatformPayment $p): array => [
                'id' => $p->id,
                'dealer_id' => $p->dealer_id,
                'dealer_name' => $p->dealer?->name ?? '—',
                'amount' => (int) $p->amount,
                'discount' => (int) $p->discount,
                'note' => $p->note,
                'created_at' => $p->created_at?->toIso8601String() ?? '',
            ])
            ->values()
            ->all();
    }
}
