<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Dealer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Diller komissiyalari hisoboti — har diller bo'yicha aylanma va komissiya.
 *
 * Daterange ichidagi:
 *  - turnover = SUM(delivered_total - discount) fulfilled buyurtmalardan
 *  - period_fee = turnover × dealer.platform_fee_rate / 100 (TURNOVER_PERCENTAGE tipida)
 *    yoki FIXED_* tipida period uchun belgilangan summa (oddiy approx — current rate × period)
 *  - paid = period ichida olingan platform_payments
 *  - balance = paid - period_fee (musbat = ortiqcha to'lov, manfiy = qarz)
 *
 * @phpstan-type CommissionFilters array{date_from?: string, date_to?: string}
 */
final class DealerCommissionReportService
{
    /**
     * @param  CommissionFilters  $filters
     * @return array{summary: array<string,int>, rows: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function generate(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $rows = $this->rows($normalized);

        $summary = [
            'dealers' => count($rows),
            'turnover' => array_sum(array_map(fn ($r) => (int) $r['turnover'], $rows)),
            'fee' => array_sum(array_map(fn ($r) => (int) $r['fee'], $rows)),
            'paid' => array_sum(array_map(fn ($r) => (int) $r['paid'], $rows)),
            'owed' => array_sum(array_map(fn ($r) => max(0, (int) $r['fee'] - (int) $r['paid']), $rows)),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
            'meta' => $normalized,
        ];
    }

    /**
     * @param  CommissionFilters  $filters
     * @return iterable<int, list<string|int|null>>
     */
    public function exportRows(array $filters): iterable
    {
        foreach ($this->generate($filters)['rows'] as $row) {
            yield [
                $row['name'],
                $row['orders'],
                $row['turnover'],
                $row['fee_rate'],
                $row['fee'],
                $row['paid'],
                $row['balance'],
            ];
        }
    }

    /**
     * @param  array{date_from: string, date_to: string}  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        // Aylanma agregati — fulfilled buyurtmalardan
        $turnoverAgg = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'dealer_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(delivered_total - COALESCE(discount, 0)), 0) as turnover'),
                DB::raw('COALESCE(AVG(platform_fee_rate), 0) as avg_rate'),
            ])
            ->groupBy('dealer_id')
            ->get()
            ->keyBy('dealer_id');

        // To'lovlar — platform_payments dan
        $paid = DB::table('platform_payments')
            ->whereBetween('created_at', [$from, $to])
            ->select(['dealer_id', DB::raw('COALESCE(SUM(amount), 0) as total')])
            ->groupBy('dealer_id')
            ->pluck('total', 'dealer_id');

        $dealers = Dealer::query()->orderBy('name')->get(['id', 'name', 'platform_fee_rate', 'is_active']);

        return $dealers->map(function (Dealer $d) use ($turnoverAgg, $paid): array {
            $agg = $turnoverAgg->get($d->id);
            $turnover = (int) round((float) ($agg->turnover ?? 0));
            $orders = (int) ($agg->orders ?? 0);
            // Snapshot rate — agar order'lar mavjud bo'lsa o'rtacha rate, aks holda joriy
            $rate = $agg !== null && (float) $agg->avg_rate > 0
                ? (float) $agg->avg_rate
                : (float) ($d->platform_fee_rate ?? 0);
            $fee = (int) round($turnover * $rate / 100);
            $paidAmount = (int) ($paid[$d->id] ?? 0);
            $balance = $paidAmount - $fee;

            return [
                'id' => $d->id,
                'name' => (string) $d->name,
                'is_active' => (bool) $d->is_active,
                'orders' => $orders,
                'turnover' => $turnover,
                'fee_rate' => round($rate, 2),
                'fee' => $fee,
                'paid' => $paidAmount,
                'balance' => $balance,
            ];
        })->sortByDesc('turnover')->values()->all();
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{date_from: string, date_to: string}
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : CarbonImmutable::now()->startOfMonth()->format('Y-m-d');

        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : CarbonImmutable::now()->format('Y-m-d');

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
}
