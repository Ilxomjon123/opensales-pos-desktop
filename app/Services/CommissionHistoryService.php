<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\DealerCommissionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Komissiya (platforma fee) tarixi.
 *
 * Har oy uchun, har bir diller uchun:
 *   - O'sha oy oxiridagi faol period topiladi
 *   - PERCENTAGE:             SUM(orders.total * orders.platform_fee_rate / 100) shu oy ichida DELIVERED bo'lganlardan
 *   - FIXED_PER_SHOP:         COUNT(DISTINCT shop_id) shu oy ichida DELIVERED orderlardan × period.fixed_commission_amount
 *   - FIXED_PER_ORDER:        COUNT(*) shu oy ichida DELIVERED orderlardan × period.fixed_commission_amount
 *   - FIXED_PER_DELIVERYMAN:  COUNT(DISTINCT deliveryman_id) shu oy ichida DELIVERED orderlardan × period.fixed_commission_amount
 *   - FIXED_MONTHLY:          period.fixed_commission_amount (faollikka bog'liq emas, shu oyda period faol bo'lsa)
 *
 * Eski oylar eski tip bo'yicha hisoblanadi — periodlar tarixini aniq saqlaydi.
 *
 * Performance: har diller uchun bitta SQL — uchchala aggregat bir GROUP BY ichida olinadi.
 * Cache: 5 daqiqa, version-key invalidation orqali.
 */
final class CommissionHistoryService
{
    private const CACHE_TTL_SECONDS = 300;

    private const VERSION_KEY = 'commission_history:version';

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Oxirgi N oy platforma darajasidagi komissiya — barcha dillerlar yig'indisi.
     *
     * @return list<array{month: string, fee_accrued: int, fee_paid: int, balance: int}>
     */
    public function platformMonthly(int $months = 12): array
    {
        return $this->cached("platform_monthly:{$months}", function () use ($months): array {
            $monthList = $this->lastNMonths($months);
            $accruedByMonth = array_fill_keys($monthList, 0);

            Dealer::query()
                ->with('commissionPeriods')
                ->chunkById(200, function ($dealers) use (&$accruedByMonth, $monthList): void {
                    foreach ($dealers as $dealer) {
                        $monthly = $this->dealerAccruedMap($dealer, $monthList);
                        foreach ($monthly as $month => $accrued) {
                            $accruedByMonth[$month] += $accrued;
                        }
                    }
                });

            $rangeStart = $this->monthStart($monthList[0]);
            $paidByMonth = $this->paidByMonth($rangeStart);

            return $this->composeMonthlyRows($monthList, $accruedByMonth, $paidByMonth);
        });
    }

    /**
     * Diller bo'yicha oxirgi N oy.
     *
     * @return list<array{month: string, fee_accrued: int, fee_paid: int, balance: int}>
     */
    public function dealerMonthly(Dealer $dealer, int $months = 12): array
    {
        return $this->cached("dealer_monthly:{$dealer->id}:{$months}", function () use ($dealer, $months): array {
            $dealer->loadMissing('commissionPeriods');

            $monthList = $this->lastNMonths($months);
            $accruedByMonth = $this->dealerAccruedMap($dealer, $monthList);

            $rangeStart = $this->monthStart($monthList[0]);
            $paidByMonth = $this->paidByMonth($rangeStart, $dealer->id);

            return $this->composeMonthlyRows($monthList, $accruedByMonth, $paidByMonth);
        });
    }

    /**
     * Barcha keshlangan hisobotlarni invalidate qiladi — version kalitini ko'taradi.
     */
    public function invalidate(): void
    {
        $this->cache->increment(self::VERSION_KEY);
    }

    /**
     * Diller uchun har oy bo'yicha hisoblangan komissiya.
     * Bitta SQL: percentage sums + distinct-shop count + order count, oy bo'yicha guruhlanib olinadi.
     *
     * @param  list<string>  $monthList  ['YYYY-MM', ...]
     * @return array<string, int>
     */
    private function dealerAccruedMap(Dealer $dealer, array $monthList): array
    {
        $result = array_fill_keys($monthList, 0);

        $periods = $dealer->commissionPeriods->sortBy('starts_at')->values();
        if ($periods->isEmpty() || $monthList === []) {
            return $result;
        }

        $rangeStart = $this->monthStart($monthList[0]);
        $rangeEnd = $this->monthEnd($monthList[count($monthList) - 1]);

        $monthExpr = SqlMonth::expression('created_at');

        $rows = DB::table('orders')
            ->where('channel', '!=', 'marketplace')
            ->where('dealer_id', $dealer->id)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->selectRaw(
                "$monthExpr AS month, "
                .'COALESCE(SUM(total * COALESCE(platform_fee_rate, 0) / 100), 0) AS fee, '
                .'COUNT(DISTINCT shop_id) AS shops, '
                .'COUNT(DISTINCT deliveryman_id) AS deliverymen, '
                .'COUNT(*) AS orders'
            )
            ->groupByRaw($monthExpr)
            ->get()
            ->keyBy('month');

        foreach ($monthList as $month) {
            $monthEndMoment = $this->monthEnd($month);

            $period = $this->findPeriodForMoment($periods, $monthEndMoment);
            if ($period === null) {
                continue;
            }

            $row = $rows->get($month);

            if ($period->commission_type === CommissionType::TURNOVER_PERCENTAGE) {
                $result[$month] = (int) round((float) ($row->fee ?? 0));

                continue;
            }

            $amount = (int) ($period->fixed_commission_amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            if ($period->commission_type === CommissionType::FIXED_MONTHLY) {
                $result[$month] = $amount;

                continue;
            }

            if ($row === null) {
                continue;
            }

            $multiplier = match ($period->commission_type) {
                CommissionType::FIXED_PER_ORDER => (int) $row->orders,
                CommissionType::FIXED_PER_DELIVERYMAN => (int) $row->deliverymen,
                default => (int) $row->shops,
            };

            $result[$month] = $multiplier * $amount;
        }

        return $result;
    }

    /**
     * Berilgan momentda faol bo'lgan periodni topadi (ends_at exclusive).
     *
     * @param  Collection<int, DealerCommissionPeriod>  $periods
     */
    private function findPeriodForMoment(Collection $periods, CarbonImmutable $moment): ?DealerCommissionPeriod
    {
        foreach ($periods as $period) {
            $startsAt = Carbon::parse($period->starts_at);
            if ($moment->lt($startsAt)) {
                continue;
            }

            if ($period->ends_at === null) {
                return $period;
            }

            if ($moment->lt(Carbon::parse($period->ends_at))) {
                return $period;
            }
        }

        return null;
    }

    /**
     * @return list<string> ['2025-05', ..., '2026-04'] eng eskidan boshlab
     */
    private function lastNMonths(int $months): array
    {
        $list = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $list[] = CarbonImmutable::now()->subMonthsNoOverflow($i)->format('Y-m');
        }

        return $list;
    }

    private function monthStart(string $ym): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
    }

    private function monthEnd(string $ym): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $ym.'-01')->endOfMonth();
    }

    /**
     * platform_payments dan oy bo'yicha jami to'lovlar.
     *
     * @return array<string, int>
     */
    private function paidByMonth(CarbonImmutable $from, ?int $dealerId = null): array
    {
        $monthExpr = SqlMonth::expression('created_at');

        $query = DB::table('platform_payments')
            ->where('created_at', '>=', $from)
            ->selectRaw("$monthExpr AS month, COALESCE(SUM(amount + discount), 0) AS paid")
            ->groupByRaw($monthExpr);

        if ($dealerId !== null) {
            $query->where('dealer_id', $dealerId);
        }

        $rows = $query->pluck('paid', 'month');

        $result = [];
        foreach ($rows as $month => $paid) {
            $result[(string) $month] = (int) $paid;
        }

        return $result;
    }

    /**
     * @param  list<string>  $monthList
     * @param  array<string, int>  $accruedByMonth
     * @param  array<string, int>  $paidByMonth
     * @return list<array{month: string, fee_accrued: int, fee_paid: int, balance: int}>
     */
    private function composeMonthlyRows(array $monthList, array $accruedByMonth, array $paidByMonth): array
    {
        $rows = [];
        foreach ($monthList as $month) {
            $accrued = (int) ($accruedByMonth[$month] ?? 0);
            $paid = (int) ($paidByMonth[$month] ?? 0);

            $rows[] = [
                'month' => $month,
                'fee_accrued' => $accrued,
                'fee_paid' => $paid,
                'balance' => $accrued - $paid,
            ];
        }

        return $rows;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function cached(string $key, \Closure $callback): mixed
    {
        $version = (int) $this->cache->get(self::VERSION_KEY, 0);

        return $this->cache->remember(
            "commission_history:v{$version}:{$key}",
            self::CACHE_TTL_SECONDS,
            $callback,
        );
    }
}
