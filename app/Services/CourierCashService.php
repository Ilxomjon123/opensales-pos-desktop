<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\CourierSettlementException;
use App\Models\CourierSettlement;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Yetkazib beruvchining qo'lidagi naqd pul (cash on hand) ledger'i.
 *
 * Logic: yetkazish paytida CASH credit payment'lar deliveryman_id bilan
 * yoziladi. Diller/owner pul topshirilganini ro'yxatga olganda
 * `courier_settlements` qatori yaratiladi.
 *
 * Balans = SUM(cash CREDIT payments) − SUM(settlements), deliveryman bo'yicha.
 *
 * Eslatma: payment va settlement ikki alohida ledger — har bir payment'ga
 * settlement biriktirilmaydi. Qisman topshirish ruxsat etilgan, summa
 * balansdan oshmasa bo'ldi.
 */
final class CourierCashService
{
    public function balanceFor(User $deliveryman): int
    {
        $collected = (int) Payment::query()
            ->courierCash()
            ->where('deliveryman_id', $deliveryman->id)
            ->sum('amount');

        $settled = (int) CourierSettlement::query()
            ->forDeliveryman($deliveryman->id)
            ->sum('amount');

        return $collected - $settled;
    }

    /**
     * Diller tashkilotidagi har bir yetkazib beruvchi uchun joriy balans.
     * Kalit — deliveryman_id, qiymat — so'm (musbat = uning qo'lida).
     *
     * Bitta UNION ALL queryda hisoblaymiz — collected va settled summalarini
     * deliveryman_id bo'yicha jamlab, farqini olamiz.
     *
     * @return array<int, int>
     */
    public function balancesForDealer(int $dealerId): array
    {
        $rows = DB::query()
            ->fromSub(function ($q) use ($dealerId) {
                $q->from('payments')
                    ->selectRaw('deliveryman_id, amount AS collected, 0 AS settled')
                    ->where('dealer_id', $dealerId)
                    ->where('method', 'cash')
                    ->where('type', 'credit')
                    ->whereNotNull('deliveryman_id')
                    ->unionAll(
                        DB::query()->from('courier_settlements')
                            ->selectRaw('deliveryman_id, 0 AS collected, amount AS settled')
                            ->where('dealer_id', $dealerId)
                    );
            }, 'l')
            ->selectRaw('deliveryman_id, SUM(collected) - SUM(settled) AS balance')
            ->groupBy('deliveryman_id')
            ->pluck('balance', 'deliveryman_id');

        $result = [];
        foreach ($rows as $id => $balance) {
            $result[(int) $id] = (int) $balance;
        }

        return $result;
    }

    public function settle(
        User $deliveryman,
        int $amount,
        ?User $by = null,
        ?string $note = null,
    ): CourierSettlement {
        if (! $deliveryman->isDeliveryman()) {
            throw CourierSettlementException::notADeliveryman();
        }

        if ($amount <= 0) {
            throw CourierSettlementException::invalidAmount();
        }

        return DB::transaction(function () use ($deliveryman, $amount, $by, $note): CourierSettlement {
            // Yetkazib beruvchi qatorini lock qilamiz — ikki parallel
            // settle() bir-birini ko'rmay nol balansdan ortiq topshirib yubormasin.
            User::query()
                ->whereKey($deliveryman->id)
                ->lockForUpdate()
                ->first(['id']);

            $available = $this->balanceFor($deliveryman);

            if ($amount > $available) {
                throw CourierSettlementException::amountExceedsBalance($amount, $available);
            }

            return CourierSettlement::query()->create([
                'dealer_id' => (int) $deliveryman->dealer_id,
                'deliveryman_id' => $deliveryman->id,
                'settled_by_user_id' => $by?->id,
                'amount' => $amount,
                'note' => $note,
                'settled_at' => now(),
            ]);
        });
    }

    /**
     * @return Collection<int, Payment>
     */
    public function recentCashPayments(User $deliveryman, int $limit = 50): Collection
    {
        return Payment::query()
            ->courierCash()
            ->where('deliveryman_id', $deliveryman->id)
            ->with(['shop:id,name,phone', 'order:id,number'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CourierSettlement>
     */
    public function settlementHistory(User $deliveryman): Collection
    {
        return CourierSettlement::query()
            ->forDeliveryman($deliveryman->id)
            ->with(['settledBy:id,name'])
            ->orderByDesc('settled_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{collected: int, settled: int, balance: int}
     */
    public function totalsFor(User $deliveryman): array
    {
        $collected = (int) Payment::query()
            ->courierCash()
            ->where('deliveryman_id', $deliveryman->id)
            ->sum('amount');

        $settled = (int) CourierSettlement::query()
            ->forDeliveryman($deliveryman->id)
            ->sum('amount');

        return [
            'collected' => $collected,
            'settled' => $settled,
            'balance' => $collected - $settled,
        ];
    }
}
