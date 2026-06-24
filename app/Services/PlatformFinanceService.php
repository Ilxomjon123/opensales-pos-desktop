<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\DealerCommissionPeriod;
use App\Models\Order;
use App\Models\PlatformPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin moliya hisob-kitoblari (komissiya, qarz, saldo).
 *
 * Komissiya periodlarga bo'linadi (`dealer_commission_periods`):
 *   TURNOVER_PERCENTAGE    — period ichidagi DELIVERED buyurtmalarning fee snapshot yig'indisi
 *   FIXED_PER_SHOP         — period ichida har oy uchun unique DELIVERED mijozlar soni × fixed_amount
 *   FIXED_PER_ORDER        — period ichidagi DELIVERED buyurtmalar soni × fixed_amount
 *   FIXED_PER_DELIVERYMAN  — period ichida har oy uchun unique DELIVERED yetkazib beruvchilar soni × fixed_amount
 *   FIXED_MONTHLY          — period qamragan har oy uchun fixed_amount (faollikka bog'liq emas)
 *
 * Saldo = (total_paid + total_discount) − fee_owed.
 *   > 0 — diller ortiqcha to'lagan
 *   < 0 — diller qarzdor
 *
 * total_discount — admin tomonidan berilgan chegirma. Naqd to'lovga ekvivalent
 * tarzda fee_owed ni kamaytiradi, lekin alohida ko'rsatkich sifatida saqlanadi.
 */
final class PlatformFinanceService
{
    /**
     * @return array{
     *     turnover: int,
     *     orders_count: int,
     *     fee_rate: float,
     *     fee_owed: int,
     *     total_paid: int,
     *     total_discount: int,
     *     balance: int,
     *     commission_type: string,
     *     fixed_commission_amount: int|null
     * }
     */
    public function snapshot(Dealer $dealer): array
    {
        $turnover = $this->turnover($dealer->id);
        $ordersCount = $this->ordersCount($dealer->id);
        $paid = $this->totalPaid($dealer->id);
        $discount = $this->totalDiscount($dealer->id);
        $owed = $this->feeOwedForDealer($dealer);

        $type = $dealer->commission_type ?? CommissionType::TURNOVER_PERCENTAGE;

        return [
            'turnover' => $turnover,
            'orders_count' => $ordersCount,
            'fee_rate' => (float) $dealer->platform_fee_rate,
            'fee_owed' => $owed,
            'total_paid' => $paid,
            'total_discount' => $discount,
            'balance' => $paid + $discount - $owed,
            'commission_type' => $type->value,
            'fixed_commission_amount' => $dealer->fixed_commission_amount !== null
                ? (int) $dealer->fixed_commission_amount
                : null,
        ];
    }

    /**
     * Diller aylanmasi — diller paneldagi total_turnover bilan mos.
     * `delivered_total - discount` fulfilled buyurtmalar bo'yicha
     * (DELIVERED + RECEIVED). `total` (zakas summasi) emas, chunki
     * haqiqiy yetkazilgan summa `delivered_total` da.
     */
    public function turnover(int $dealerId): int
    {
        $agg = Order::query()
            ->shopChannel()
            ->where('dealer_id', $dealerId)
            ->fulfilled()
            ->selectRaw('COALESCE(SUM(delivered_total), 0) AS gross, COALESCE(SUM(discount), 0) AS disc')
            ->first();

        return (int) (($agg->gross ?? 0) - ($agg->disc ?? 0));
    }

    public function ordersCount(int $dealerId): int
    {
        return Order::query()
            ->shopChannel()
            ->where('dealer_id', $dealerId)
            ->fulfilled()
            ->count();
    }

    public function totalPaid(int $dealerId): int
    {
        return (int) PlatformPayment::query()
            ->where('dealer_id', $dealerId)
            ->sum('amount');
    }

    public function totalDiscount(int $dealerId): int
    {
        return (int) PlatformPayment::query()
            ->where('dealer_id', $dealerId)
            ->sum('discount');
    }

    /**
     * Diller komissiyasi — barcha periodlar bo'yicha to'plangan yig'indi.
     * Periodlar eager-load qilingan bo'lishi tavsiya etiladi.
     */
    public function feeOwedForDealer(Dealer $dealer): int
    {
        $periods = $dealer->relationLoaded('commissionPeriods')
            ? $dealer->commissionPeriods->sortBy('starts_at')->values()
            : $dealer->commissionPeriods()->orderBy('starts_at')->get();

        $total = 0;
        foreach ($periods as $period) {
            $total += $this->feeForPeriod($dealer->id, $period);
        }

        return $total;
    }

    /**
     * Bitta period uchun jami komissiya (1 ta SQL).
     */
    public function feeForPeriod(int $dealerId, DealerCommissionPeriod $period): int
    {
        [$start, $end] = $this->periodBounds($period);

        if ($period->commission_type === CommissionType::TURNOVER_PERCENTAGE) {
            $value = DB::table('orders')
                ->where('channel', '!=', 'marketplace')
                ->where('dealer_id', $dealerId)
                ->where('status', OrderStatus::DELIVERED->value)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(total * COALESCE(platform_fee_rate, 0) / 100), 0) AS fee')
                ->value('fee');

            return (int) round((float) $value);
        }

        $amount = (int) ($period->fixed_commission_amount ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        if ($period->commission_type === CommissionType::FIXED_PER_ORDER) {
            $orderCount = DB::table('orders')
                ->where('channel', '!=', 'marketplace')
                ->where('dealer_id', $dealerId)
                ->where('status', OrderStatus::DELIVERED->value)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            return $orderCount * $amount;
        }

        if ($period->commission_type === CommissionType::FIXED_PER_DELIVERYMAN) {
            $deliverymanMonths = $this->sumDistinctDeliverymenPerMonth($dealerId, $start, $end);

            return $deliverymanMonths * $amount;
        }

        if ($period->commission_type === CommissionType::FIXED_MONTHLY) {
            return $this->monthsInRange($start, $end) * $amount;
        }

        $shopMonths = $this->sumDistinctShopsPerMonth($dealerId, $start, $end);

        return $shopMonths * $amount;
    }

    /**
     * Period [start, end] qamragan oylar soni. Qisman oy ham 1 oy sifatida hisoblanadi
     * (FIXED_PER_SHOP/DELIVERYMAN ning oy bo'yicha hisoblash mantig'iga mos).
     */
    private function monthsInRange(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $startMoment = Carbon::parse($start)->startOfMonth();
        $endMoment = Carbon::parse($end);

        if ($endMoment->lt($startMoment)) {
            return 0;
        }

        $endMoment = $endMoment->copy()->startOfMonth();

        return (int) $startMoment->diffInMonths($endMoment) + 1;
    }

    /**
     * Barcha dillerlar bo'yicha umumiy ko'rsatkichlar.
     *
     * @return array{turnover: int, fee_owed: int, total_paid: int, total_discount: int, balance: int}
     */
    public function totals(): array
    {
        $turnoverAgg = Order::query()
            ->shopChannel()
            ->fulfilled()
            ->selectRaw('COALESCE(SUM(delivered_total), 0) AS gross, COALESCE(SUM(discount), 0) AS disc')
            ->first();

        $turnover = (int) (($turnoverAgg->gross ?? 0) - ($turnoverAgg->disc ?? 0));

        $feeOwed = 0;
        Dealer::query()
            ->with('commissionPeriods')
            ->chunkById(200, function ($dealers) use (&$feeOwed): void {
                foreach ($dealers as $dealer) {
                    $feeOwed += $this->feeOwedForDealer($dealer);
                }
            });

        $paid = (int) PlatformPayment::query()->sum('amount');
        $discount = (int) PlatformPayment::query()->sum('discount');

        return [
            'turnover' => $turnover,
            'fee_owed' => $feeOwed,
            'total_paid' => $paid,
            'total_discount' => $discount,
            'balance' => $paid + $discount - $feeOwed,
        ];
    }

    /**
     * Platform moliyasi valyuta bo'yicha guruhlangan (aralash valyuta uchun).
     *
     * @return list<array{currency: string, symbol: string, turnover: int, fee_owed: int, total_paid: int, total_discount: int, balance: int}>
     */
    public function totalsByCurrency(): array
    {
        $turnoverRows = Order::query()
            ->shopChannel()
            ->fulfilled()
            ->selectRaw('currency, COALESCE(SUM(delivered_total), 0) AS gross, COALESCE(SUM(discount), 0) AS disc')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $feeByCurrency = [];
        Dealer::query()
            ->with('commissionPeriods')
            ->chunkById(200, function ($dealers) use (&$feeByCurrency): void {
                foreach ($dealers as $dealer) {
                    $code = $dealer->currency instanceof Currency ? $dealer->currency->value : (string) $dealer->currency;
                    $feeByCurrency[$code] = ($feeByCurrency[$code] ?? 0) + $this->feeOwedForDealer($dealer);
                }
            });

        $payRows = PlatformPayment::query()
            ->selectRaw('currency, COALESCE(SUM(amount), 0) AS paid, COALESCE(SUM(discount), 0) AS disc')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $codes = collect($turnoverRows->keys())
            ->merge($payRows->keys())
            ->merge(array_keys($feeByCurrency))
            ->unique()
            ->filter()
            ->values();

        return $codes->map(function (string $code) use ($turnoverRows, $feeByCurrency, $payRows): array {
            $currency = Currency::tryFrom($code) ?? Currency::UZS;
            $turnover = (int) (($turnoverRows->get($code)->gross ?? 0) - ($turnoverRows->get($code)->disc ?? 0));
            $feeOwed = (int) ($feeByCurrency[$code] ?? 0);
            $paid = (int) ($payRows->get($code)->paid ?? 0);
            $discount = (int) ($payRows->get($code)->disc ?? 0);

            return [
                'currency' => $code,
                'symbol' => $currency->symbol(),
                'turnover' => $turnover,
                'fee_owed' => $feeOwed,
                'total_paid' => $paid,
                'total_discount' => $discount,
                'balance' => $paid + $discount - $feeOwed,
            ];
        })->all();
    }

    /**
     * Berilgan diapazonda har oy uchun unique mijozlar yig'indisi (1 ta SQL).
     * Misol: aprel 5 mijoz, may 7 mijoz → 12.
     */
    private function sumDistinctShopsPerMonth(int $dealerId, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $monthExpr = SqlMonth::expression('orders.created_at');

        $rows = DB::table('orders')
            ->where('orders.channel', '!=', 'marketplace')
            ->where('dealer_id', $dealerId)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$monthExpr AS month, COUNT(DISTINCT shop_id) AS shops")
            ->groupByRaw($monthExpr)
            ->get();

        return (int) $rows->sum('shops');
    }

    /**
     * Berilgan diapazonda har oy uchun unique yetkazib beruvchilar yig'indisi (1 ta SQL).
     * NULL deliveryman_id COUNT(DISTINCT) tomonidan e'tiborga olinmaydi.
     */
    private function sumDistinctDeliverymenPerMonth(int $dealerId, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $monthExpr = SqlMonth::expression('orders.created_at');

        $rows = DB::table('orders')
            ->where('orders.channel', '!=', 'marketplace')
            ->where('dealer_id', $dealerId)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$monthExpr AS month, COUNT(DISTINCT deliveryman_id) AS deliverymen")
            ->groupByRaw($monthExpr)
            ->get();

        return (int) $rows->sum('deliverymen');
    }

    /**
     * @return array{0: \DateTimeInterface, 1: \DateTimeInterface}
     */
    private function periodBounds(DealerCommissionPeriod $period): array
    {
        $start = Carbon::parse($period->starts_at);
        $end = $period->ends_at !== null ? Carbon::parse($period->ends_at) : Carbon::now();

        return [$start, $end];
    }
}
