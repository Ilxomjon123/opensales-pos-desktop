<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Platforma sotuv hisoboti — barcha dillerlar bo'yicha cross-dealer.
 *
 * SalesReportService bilan o'xshash, lekin dealer_id filter optional —
 * default barcha dillerlar. Super admin uchun.
 *
 * @phpstan-type PlatformSalesFilters array{
 *     date_from?: string, date_to?: string, group_by?: string, dealer_id?: ?int,
 * }
 */
final class PlatformSalesReportService
{
    public const GROUP_DAY = 'day';

    public const GROUP_WEEK = 'week';

    public const GROUP_MONTH = 'month';

    public const GROUP_DEALER = 'dealer';

    public const GROUP_BY_OPTIONS = [self::GROUP_DAY, self::GROUP_WEEK, self::GROUP_MONTH, self::GROUP_DEALER];

    /**
     * @param  PlatformSalesFilters  $filters
     * @return array{summary: array<string,int>, rows: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function generate(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        return [
            'summary' => $this->summary($normalized),
            'rows' => $this->rows($normalized),
            'meta' => $normalized,
        ];
    }

    /**
     * @param  PlatformSalesFilters  $filters
     * @return iterable<int, list<string|int|null>>
     */
    public function exportRows(array $filters): iterable
    {
        foreach ($this->generate($filters)['rows'] as $row) {
            yield [
                $row['label'],
                $row['orders'],
                $row['gross'],
                $row['discount'],
                $row['net'],
                $row['aov'],
            ];
        }
    }

    /**
     * @param  array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}  $filters
     * @return array<string,int>
     */
    private function summary(array $filters): array
    {
        $agg = (clone $this->baseQuery($filters))
            ->selectRaw('
                COUNT(*) as orders,
                COALESCE(SUM(delivered_total), 0) as gross,
                COALESCE(SUM(discount), 0) as discount
            ')
            ->first();

        $gross = (int) ($agg->gross ?? 0);
        $discount = (int) ($agg->discount ?? 0);
        $orders = (int) ($agg->orders ?? 0);
        $net = $gross - $discount;

        return [
            'orders' => $orders,
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'aov' => $orders > 0 ? (int) round($net / $orders) : 0,
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(array $filters): array
    {
        return match ($filters['group_by']) {
            self::GROUP_DEALER => $this->groupByDealer($filters),
            default => $this->groupByPeriod($filters),
        };
    }

    /**
     * @param  array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByPeriod(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(discount), 0) as discount'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get();

        $buckets = [];
        foreach ($rows as $r) {
            $key = $this->bucketKey((string) $r->date, $filters['group_by']);
            $buckets[$key] ??= ['label' => $key, 'orders' => 0, 'gross' => 0, 'discount' => 0];
            $buckets[$key]['orders'] += (int) $r->orders;
            $buckets[$key]['gross'] += (int) $r->gross;
            $buckets[$key]['discount'] += (int) $r->discount;
        }

        return array_values(array_map(function (array $b): array {
            $net = $b['gross'] - $b['discount'];

            return [
                'label' => $b['label'],
                'orders' => $b['orders'],
                'gross' => $b['gross'],
                'discount' => $b['discount'],
                'net' => $net,
                'aov' => $b['orders'] > 0 ? (int) round($net / $b['orders']) : 0,
            ];
        }, $buckets));
    }

    /**
     * @param  array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByDealer(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->join('dealers', 'dealers.id', '=', 'orders.dealer_id')
            ->select([
                'orders.dealer_id',
                DB::raw('MAX(dealers.name) as label'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(orders.delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(orders.discount), 0) as discount'),
            ])
            ->groupBy('orders.dealer_id')
            ->orderByDesc(DB::raw('SUM(orders.delivered_total - COALESCE(orders.discount, 0))'))
            ->get();

        return $rows->map(function ($r): array {
            $net = (int) $r->gross - (int) $r->discount;

            return [
                'id' => (int) $r->dealer_id,
                'label' => (string) $r->label,
                'orders' => (int) $r->orders,
                'gross' => (int) $r->gross,
                'discount' => (int) $r->discount,
                'net' => $net,
                'aov' => (int) $r->orders > 0 ? (int) round($net / (int) $r->orders) : 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}  $filters
     * @return Builder<Order>
     */
    private function baseQuery(array $filters): Builder
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        return Order::query()
            ->whereIn('orders.status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($filters['dealer_id'] !== null, fn ($q) => $q->where('orders.dealer_id', $filters['dealer_id']));
    }

    private function bucketKey(string $date, string $groupBy): string
    {
        $d = CarbonImmutable::parse($date);

        return match ($groupBy) {
            self::GROUP_WEEK => $d->format('o-\WW'),
            self::GROUP_MONTH => $d->format('Y-m'),
            default => $d->format('Y-m-d'),
        };
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{date_from: string, date_to: string, group_by: string, dealer_id: ?int}
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : CarbonImmutable::now()->subDays(29)->format('Y-m-d');

        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : CarbonImmutable::now()->format('Y-m-d');

        $groupBy = isset($filters['group_by']) && in_array($filters['group_by'], self::GROUP_BY_OPTIONS, true)
            ? (string) $filters['group_by']
            : self::GROUP_DAY;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'group_by' => $groupBy,
            'dealer_id' => isset($filters['dealer_id']) && $filters['dealer_id'] !== '' ? (int) $filters['dealer_id'] : null,
        ];
    }
}
