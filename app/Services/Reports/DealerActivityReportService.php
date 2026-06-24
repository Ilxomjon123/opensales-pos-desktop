<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Dealer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Diller faollik hisoboti — har diller bo'yicha engagement metrikalar.
 *
 * Daterange ichidagi:
 *  - orders_count, shops_with_orders (MAU shops), revenue
 *  - frequency_per_month (orders × 30 / period_days)
 * Barcha vaqt bo'yicha:
 *  - shops_count (umumiy)
 *  - last_order_at, days_since
 * Faollik holati (last_order_at asosida):
 *  - active: ≤7 kun
 *  - at_risk: 8–30 kun
 *  - inactive: 30+ kun yoki hech qachon zakas bermagan
 *
 * @phpstan-type ActivityFilters array{
 *     date_from?: string, date_to?: string,
 *     status?: string|null,
 * }
 */
final class DealerActivityReportService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_AT_RISK = 'at_risk';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_OPTIONS = [self::STATUS_ACTIVE, self::STATUS_AT_RISK, self::STATUS_INACTIVE];

    private const AT_RISK_DAYS = 7;

    private const INACTIVE_DAYS = 30;

    /**
     * @param  ActivityFilters  $filters
     * @return array{summary: array<string,int>, rows: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function generate(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->rows($normalized);

        $summary = [
            'dealers' => count($rows),
            'active' => count(array_filter($rows, fn ($r) => $r['status'] === self::STATUS_ACTIVE)),
            'at_risk' => count(array_filter($rows, fn ($r) => $r['status'] === self::STATUS_AT_RISK)),
            'inactive' => count(array_filter($rows, fn ($r) => $r['status'] === self::STATUS_INACTIVE)),
            'total_revenue' => array_sum(array_map(fn ($r) => (int) $r['revenue'], $rows)),
            'total_orders' => array_sum(array_map(fn ($r) => (int) $r['orders'], $rows)),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
            'meta' => $normalized,
        ];
    }

    /**
     * @param  ActivityFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(array $filters): iterable
    {
        foreach ($this->generate($filters)['rows'] as $row) {
            yield [
                $row['name'],
                $row['is_active'] ? 'ha' : 'yo\'q',
                $row['shops_count'],
                $row['mau_shops'],
                $row['orders'],
                $row['revenue'],
                $row['frequency_per_month'],
                $row['last_order_at'] ?? '',
                $row['days_since'] ?? '',
                $row['status'],
            ];
        }
    }

    /**
     * @param  array{date_from: string, date_to: string, status: ?string}  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $periodDays = max(1, (int) $from->diffInDays($to->endOfDay()) + 1);

        // Aylanma + zakaslar (period)
        $orderAgg = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'dealer_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('COUNT(DISTINCT shop_id) as mau_shops'),
                DB::raw('COALESCE(SUM(delivered_total - COALESCE(discount, 0)), 0) as revenue'),
            ])
            ->groupBy('dealer_id')
            ->get()
            ->keyBy('dealer_id');

        // Barcha vaqt bo'yicha oxirgi zakas
        $lastOrder = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->select('dealer_id', DB::raw('MAX(created_at) as last_order_at'))
            ->groupBy('dealer_id')
            ->pluck('last_order_at', 'dealer_id');

        // Umumiy shoplar soni
        $shopCounts = DB::table('shops')
            ->select('dealer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('dealer_id')
            ->pluck('cnt', 'dealer_id');

        $now = CarbonImmutable::now();

        $rows = Dealer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(function (Dealer $d) use ($orderAgg, $lastOrder, $shopCounts, $now, $periodDays): array {
                $agg = $orderAgg->get($d->id);
                $orders = (int) ($agg->orders ?? 0);
                $mauShops = (int) ($agg->mau_shops ?? 0);
                $revenue = (int) round((float) ($agg->revenue ?? 0));

                $lastOrderRaw = $lastOrder->get($d->id);
                $lastOrderAt = $lastOrderRaw !== null ? (string) $lastOrderRaw : null;
                $daysSince = $lastOrderAt !== null
                    ? max(0, (int) CarbonImmutable::parse($lastOrderAt)->diffInDays($now))
                    : null;

                $status = $this->classify($daysSince);

                return [
                    'id' => $d->id,
                    'name' => (string) $d->name,
                    'is_active' => (bool) $d->is_active,
                    'shops_count' => (int) ($shopCounts[$d->id] ?? 0),
                    'mau_shops' => $mauShops,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'frequency_per_month' => round($orders * 30 / $periodDays, 2),
                    'last_order_at' => $lastOrderAt,
                    'days_since' => $daysSince,
                    'status' => $status,
                ];
            })
            ->values()
            ->all();

        if ($filters['status'] !== null) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['status'] === $filters['status']));
        }

        return $rows;
    }

    private function classify(?int $daysSince): string
    {
        if ($daysSince === null) {
            return self::STATUS_INACTIVE;
        }

        if ($daysSince <= self::AT_RISK_DAYS) {
            return self::STATUS_ACTIVE;
        }

        if ($daysSince <= self::INACTIVE_DAYS) {
            return self::STATUS_AT_RISK;
        }

        return self::STATUS_INACTIVE;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{date_from: string, date_to: string, status: ?string}
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : CarbonImmutable::now()->subDays(29)->format('Y-m-d');

        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : CarbonImmutable::now()->format('Y-m-d');

        $status = isset($filters['status']) && in_array($filters['status'], self::STATUS_OPTIONS, true)
            ? (string) $filters['status']
            : null;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
        ];
    }
}
