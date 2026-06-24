<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mijozlar hisoboti — har do'kon kesimi.
 *
 * Period ichidagi:
 *  - orders_count, gross, discount, net = SUM(delivered_total/discount) fulfilled
 *  - aov = net / orders_count
 * Real-time:
 *  - balance (joriy qarz/oldindan to'lov)
 *  - last_order_at — barcha vaqt bo'yicha oxirgi zakas
 *  - days_since — oxirgi zakasdan o'tgan kunlar
 *  - frequency_per_month — period orders × 30 / period_days (approximate)
 * ABC tasniflash (period revenue bo'yicha kumulyativ):
 *   A = top 20% to'plamida (kumulyativ revenue 80% gacha)
 *   B = keyingi 30% (80% → 95% gacha)
 *   C = qolgani
 *
 * @phpstan-type CustomerFilters array{
 *     date_from?: string, date_to?: string,
 *     activity?: string|null, region?: string|null, district?: string|null,
 * }
 */
final class CustomersReportService
{
    public const ACTIVITY_ACTIVE = 'active';

    public const ACTIVITY_AT_RISK = 'at_risk';

    public const ACTIVITY_INACTIVE = 'inactive';

    public const ACTIVITY_OPTIONS = [
        self::ACTIVITY_ACTIVE,
        self::ACTIVITY_AT_RISK,
        self::ACTIVITY_INACTIVE,
    ];

    private const AT_RISK_DAYS = 14;

    private const INACTIVE_DAYS = 30;

    /**
     * @param  CustomerFilters  $filters
     * @return array{
     *     summary: array<string,int|float>,
     *     rows: list<array<string,mixed>>,
     *     meta: array<string,mixed>,
     * }
     */
    public function generate(int $dealerId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->rows($dealerId, $normalized);

        $totalRevenue = array_sum(array_map(fn ($r) => (int) $r['net'], $rows));
        $totalDebt = array_sum(array_map(fn ($r) => max(0, -((int) $r['balance'])), $rows));

        return [
            'summary' => [
                'shops' => count($rows),
                'a_count' => count(array_filter($rows, fn ($r) => $r['tier'] === 'A')),
                'b_count' => count(array_filter($rows, fn ($r) => $r['tier'] === 'B')),
                'c_count' => count(array_filter($rows, fn ($r) => $r['tier'] === 'C')),
                'active_count' => count(array_filter($rows, fn ($r) => $r['activity'] === self::ACTIVITY_ACTIVE)),
                'at_risk_count' => count(array_filter($rows, fn ($r) => $r['activity'] === self::ACTIVITY_AT_RISK)),
                'inactive_count' => count(array_filter($rows, fn ($r) => $r['activity'] === self::ACTIVITY_INACTIVE)),
                'revenue' => $totalRevenue,
                'debt' => $totalDebt,
            ],
            'rows' => $rows,
            'meta' => $normalized,
        ];
    }

    /**
     * @param  CustomerFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        foreach ($this->generate($dealerId, $filters)['rows'] as $row) {
            yield [
                $row['name'],
                $row['region'] ?? '—',
                $row['district'] ?? '—',
                $row['orders'],
                $row['gross'],
                $row['discount'],
                $row['net'],
                $row['aov'],
                $row['frequency_per_month'],
                $row['last_order_at'] ?? '',
                $row['days_since'] ?? '',
                $row['balance'],
                $row['tier'],
                $row['activity'],
            ];
        }
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     activity: ?string, region: ?string, district: ?string,
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $periodDays = max(1, (int) $from->diffInDays($to->endOfDay()) + 1);

        // Period kesimida har do'kon bo'yicha buyurtma agregati.
        $orderAgg = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->where('dealer_id', $dealerId)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'shop_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(discount), 0) as discount'),
            ])
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        // Barcha vaqt bo'yicha oxirgi zakas — activity uchun.
        $lastOrder = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->where('dealer_id', $dealerId)
            ->select('shop_id', DB::raw('MAX(created_at) as last_order_at'))
            ->groupBy('shop_id')
            ->pluck('last_order_at', 'shop_id');

        $shopQuery = Shop::query()
            ->forDealer($dealerId)
            ->orderBy('name');

        if ($filters['region'] !== null && $filters['region'] !== '') {
            $shopQuery->where('region', $filters['region']);
        }

        if ($filters['district'] !== null && $filters['district'] !== '') {
            $shopQuery->where('district', $filters['district']);
        }

        $shops = $shopQuery->get(['id', 'name', 'region', 'district', 'balance', 'is_active']);

        $now = CarbonImmutable::now();

        $rows = $shops->map(function (Shop $s) use ($orderAgg, $lastOrder, $now, $periodDays): array {
            $agg = $orderAgg->get($s->id);
            $orders = (int) ($agg->orders ?? 0);
            $gross = (int) ($agg->gross ?? 0);
            $discount = (int) ($agg->discount ?? 0);
            $net = $gross - $discount;
            $aov = $orders > 0 ? (int) round($net / $orders) : 0;
            $frequencyPerMonth = round($orders * 30 / $periodDays, 2);

            $lastOrderRaw = $lastOrder->get($s->id);
            $lastOrderAt = $lastOrderRaw !== null ? (string) $lastOrderRaw : null;
            $daysSince = $lastOrderAt !== null
                ? max(0, (int) CarbonImmutable::parse($lastOrderAt)->diffInDays($now))
                : null;

            $activity = $this->activity($daysSince);

            return [
                'id' => $s->id,
                'name' => (string) $s->name,
                'region' => $s->region,
                'district' => $s->district,
                'orders' => $orders,
                'gross' => $gross,
                'discount' => $discount,
                'net' => $net,
                'aov' => $aov,
                'frequency_per_month' => $frequencyPerMonth,
                'last_order_at' => $lastOrderAt,
                'days_since' => $daysSince,
                'balance' => (int) $s->balance,
                'tier' => 'C', // placeholder, ABC pastda hisoblanadi
                'activity' => $activity,
            ];
        })->values()->all();

        // Activity filter — keyin qo'llaymiz.
        if ($filters['activity'] !== null && in_array($filters['activity'], self::ACTIVITY_OPTIONS, true)) {
            $rows = array_values(array_filter($rows, fn (array $r): bool => $r['activity'] === $filters['activity']));
        }

        return $this->assignAbcTiers($rows);
    }

    /**
     * Kumulyativ revenue bo'yicha ABC tasniflash.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function assignAbcTiers(array $rows): array
    {
        // Net bo'yicha tartiblash, ABC kumulyativ chegaralar bilan.
        usort($rows, fn ($a, $b): int => $b['net'] <=> $a['net']);

        $totalRevenue = array_sum(array_map(fn ($r) => (int) $r['net'], $rows));

        if ($totalRevenue <= 0) {
            return array_map(function (array $r): array {
                $r['tier'] = 'C';

                return $r;
            }, $rows);
        }

        // Tier — kumulyativ chegarani _kesib o'tgan_ qator A da qoladi
        // (oldingi kumulyatsiya % bo'yicha hisoblanadi).
        $cumulative = 0;
        $result = [];

        foreach ($rows as $row) {
            $prevPct = $cumulative / $totalRevenue * 100;

            $row['tier'] = match (true) {
                $prevPct < 80 => 'A',
                $prevPct < 95 => 'B',
                default => 'C',
            };

            $cumulative += (int) $row['net'];
            $result[] = $row;
        }

        return $result;
    }

    private function activity(?int $daysSince): string
    {
        if ($daysSince === null) {
            return self::ACTIVITY_INACTIVE;
        }

        if ($daysSince <= self::AT_RISK_DAYS) {
            return self::ACTIVITY_ACTIVE;
        }

        if ($daysSince <= self::INACTIVE_DAYS) {
            return self::ACTIVITY_AT_RISK;
        }

        return self::ACTIVITY_INACTIVE;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *     date_from: string, date_to: string,
     *     activity: ?string, region: ?string, district: ?string,
     * }
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : CarbonImmutable::now()->subDays(89)->format('Y-m-d');

        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : CarbonImmutable::now()->format('Y-m-d');

        $activity = isset($filters['activity']) && in_array($filters['activity'], self::ACTIVITY_OPTIONS, true)
            ? (string) $filters['activity']
            : null;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'activity' => $activity,
            'region' => isset($filters['region']) && $filters['region'] !== '' ? (string) $filters['region'] : null,
            'district' => isset($filters['district']) && $filters['district'] !== '' ? (string) $filters['district'] : null,
        ];
    }
}
