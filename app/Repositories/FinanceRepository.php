<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class FinanceRepository
{
    /**
     * Yetkazib berishi kutilayotgan buyurtmalar — saldoga tegmagan,
     * lekin tez orada qarzga aylanadigan summalar.
     */
    private const array PENDING_STATUSES = [
        OrderStatus::PENDING,
        OrderStatus::ASSEMBLING,
        OrderStatus::DELIVERING,
    ];

    public function paginateForDealer(int $dealerId, int $perPage = 30): LengthAwarePaginator
    {
        return Payment::query()
            ->where('dealer_id', $dealerId)
            ->with('shop')
            ->latest()
            ->paginate($perPage);
    }

    public function shopBalance(int $shopId): int
    {
        $credits = (int) Payment::query()
            ->where('shop_id', $shopId)
            ->where('type', PaymentType::CREDIT)
            ->sum('amount');

        $debits = (int) Payment::query()
            ->where('shop_id', $shopId)
            ->where('type', PaymentType::DEBIT)
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * Bitta mijoz uchun yetkazilishi kutilayotgan buyurtmalar yig'indisi (so'mda).
     */
    public function shopPendingTotal(int $shopId): int
    {
        return (int) Order::query()
            ->shopChannel()
            ->where('shop_id', $shopId)
            ->whereIn('status', self::PENDING_STATUSES)
            ->sum('total');
    }

    /**
     * Diller bo'yicha mijozlarning kutilayotgan buyurtmalar yig'indisi.
     *
     * @param  array<int, int>  $shopIds  bo'sh bo'lsa — diller barcha mijozlari
     * @return array<int, int> shopId => sum
     */
    public function pendingTotalsByShop(int $dealerId, array $shopIds = []): array
    {
        return Order::query()
            ->shopChannel()
            ->where('dealer_id', $dealerId)
            ->whereIn('status', self::PENDING_STATUSES)
            ->when($shopIds !== [], fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->groupBy('shop_id')
            ->selectRaw('shop_id, SUM(total) AS total')
            ->pluck('total', 'shop_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
