<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentType;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Qarzdorlar aging hisoboti.
 * Har mijozning so'nggi to'lovidan (CREDIT) beri o'tgan kunlarni hisoblab,
 * 4 ta bakketga ajratadi: 0-30, 31-60, 61-90, 90+.
 * Hech to'lov bermaganlar — birinchi buyurtmadan beri o'tgan kunlar bo'yicha.
 */
final class DebtAgingService
{
    public const BUCKETS = [
        'current' => 30,     // 0-30 kun
        'warning' => 60,     // 31-60 kun
        'late' => 90,        // 61-90 kun
        'critical' => null,  // 90+
    ];

    /**
     * @return array{
     *     totals: array{count: int, debt: int},
     *     buckets: array<string, array{count: int, debt: int}>,
     *     rows: list<array{shop_id: int, name: string, balance: int, last_payment_at: string|null, days_since: int, bucket: string}>
     * }
     */
    public function report(int $dealerId): array
    {
        $now = CarbonImmutable::now();

        $lastCredit = DB::table('payments')
            ->where('dealer_id', $dealerId)
            ->where('type', PaymentType::CREDIT->value)
            ->select('shop_id', DB::raw('MAX(created_at) as last_payment_at'))
            ->groupBy('shop_id');

        $firstOrder = DB::table('orders')
            ->where('dealer_id', $dealerId)
            ->select('shop_id', DB::raw('MIN(created_at) as first_order_at'))
            ->groupBy('shop_id');

        $shops = Shop::query()
            ->forDealer($dealerId)
            ->where('balance', '<', 0)
            ->leftJoinSub($lastCredit, 'lc', 'lc.shop_id', '=', 'shops.id')
            ->leftJoinSub($firstOrder, 'fo', 'fo.shop_id', '=', 'shops.id')
            ->orderBy('shops.balance')
            ->get([
                'shops.id', 'shops.name', 'shops.balance',
                DB::raw('lc.last_payment_at as last_payment_at'),
                DB::raw('fo.first_order_at as first_order_at'),
            ]);

        $rows = [];
        $buckets = array_fill_keys(array_keys(self::BUCKETS), ['count' => 0, 'debt' => 0]);
        $totalDebt = 0;

        foreach ($shops as $s) {
            $anchor = $s->last_payment_at ?? $s->first_order_at;

            if ($anchor === null) {
                continue;
            }

            // Carbon 3 da diffInDays ishorali (signed). Kelajakdagi sanalar uchun 0 ga aylantirish.
            $days = max(0, (int) CarbonImmutable::parse((string) $anchor)->diffInDays($now));
            $bucket = $this->bucketFor($days);
            $debt = abs((int) $s->balance);

            $rows[] = [
                'shop_id' => (int) $s->id,
                'name' => (string) $s->name,
                'balance' => (int) $s->balance,
                'last_payment_at' => $s->last_payment_at !== null ? (string) $s->last_payment_at : null,
                'days_since' => $days,
                'bucket' => $bucket,
            ];

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['debt'] += $debt;
            $totalDebt += $debt;
        }

        return [
            'totals' => ['count' => count($rows), 'debt' => $totalDebt],
            'buckets' => $buckets,
            'rows' => $rows,
        ];
    }

    private function bucketFor(int $days): string
    {
        foreach (self::BUCKETS as $name => $max) {
            if ($max === null || $days <= $max) {
                return $name;
            }
        }

        return 'critical';
    }
}
