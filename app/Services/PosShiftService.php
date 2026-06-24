<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PosShiftStatus;
use App\Enums\SaleChannel;
use App\Exceptions\Domain\PosShiftException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PosShiftService
{
    /**
     * Faol (ochiq) smenani topadi. Bitta kassirda bir vaqtda faqat 1 ta ochiq smena bo'lishi mumkin.
     */
    public function getActive(int $dealerId, int $cashierUserId): ?PosShift
    {
        return PosShift::query()
            ->forDealer($dealerId)
            ->forCashier($cashierUserId)
            ->open()
            ->latest('opened_at')
            ->first();
    }

    public function open(User $cashier, int $openingCash, ?string $note = null): PosShift
    {
        if ($cashier->dealer_id === null) {
            throw PosShiftException::noOpenShift();
        }

        if ($this->getActive((int) $cashier->dealer_id, $cashier->id) !== null) {
            throw PosShiftException::alreadyOpen();
        }

        return PosShift::query()->create([
            'dealer_id' => $cashier->dealer_id,
            'cashier_user_id' => $cashier->id,
            'status' => PosShiftStatus::OPEN,
            'opened_at' => now(),
            'opening_cash' => max(0, $openingCash),
            'opening_note' => $note,
        ]);
    }

    /**
     * Smenani yopish — kutilgan naqd summa hisoblanadi va farq yoziladi.
     * `expectedCash = opening_cash + total_cash` (qaytarish/return keyingi sprintda).
     */
    public function close(PosShift $shift, int $closingCash, ?string $note = null): PosShift
    {
        if (! $shift->isOpen()) {
            throw PosShiftException::notOpen();
        }

        return DB::transaction(function () use ($shift, $closingCash, $note): PosShift {
            $totals = $this->aggregateTotals($shift);
            $expectedCash = (int) $shift->opening_cash + $totals['total_cash'];

            $shift->update([
                'status' => PosShiftStatus::CLOSED,
                'closed_at' => now(),
                'closing_cash' => max(0, $closingCash),
                'expected_cash' => $expectedCash,
                'cash_diff' => max(0, $closingCash) - $expectedCash,
                'total_sales' => $totals['total_sales'],
                'total_cash' => $totals['total_cash'],
                'total_card' => $totals['total_card'],
                'total_debt' => $totals['total_debt'],
                'sales_count' => $totals['sales_count'],
                'closing_note' => $note,
            ]);

            return $shift->refresh();
        });
    }

    /**
     * Ochiq smena uchun jonli statistika (Kassir UI'da ko'rsatish uchun).
     *
     * @return array{total_sales: int, total_cash: int, total_card: int, total_debt: int, sales_count: int, expected_cash: int}
     */
    public function liveStats(PosShift $shift): array
    {
        $totals = $this->aggregateTotals($shift);
        $totals['expected_cash'] = (int) $shift->opening_cash + $totals['total_cash'];

        return $totals;
    }

    /**
     * @return array{total_sales: int, total_cash: int, total_card: int, total_debt: int, sales_count: int}
     */
    private function aggregateTotals(PosShift $shift): array
    {
        $sales = Order::query()
            ->forShift($shift->id)
            ->where('sale_channel', SaleChannel::POS)
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_cash),0) as cash, COALESCE(SUM(paid_card),0) as card, COALESCE(SUM(debt_amount),0) as debt')
            ->first();

        // Qarzga sotuvdan keyin tushgan to'lovlar (FinanceService::credit) ham smenaga tegishli
        $latePayments = Payment::query()
            ->forShift($shift->id)
            ->where('type', PaymentType::CREDIT)
            ->whereNull('order_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN method = ? THEN amount ELSE 0 END),0) as cash, COALESCE(SUM(CASE WHEN method = ? THEN amount ELSE 0 END),0) as card', [
                PaymentMethod::CASH->value,
                PaymentMethod::CARD->value,
            ])
            ->first();

        return [
            'total_sales' => (int) ($sales->total ?? 0),
            'total_cash' => (int) ($sales->cash ?? 0) + (int) ($latePayments->cash ?? 0),
            'total_card' => (int) ($sales->card ?? 0) + (int) ($latePayments->card ?? 0),
            'total_debt' => (int) ($sales->debt ?? 0),
            'sales_count' => (int) ($sales->cnt ?? 0),
        ];
    }

    /**
     * X-hisobot: smena hali ochiq, oraliq jonli statistika ko'rsatish uchun.
     *
     * @return array{shift: PosShift, totals: array, payment_status_breakdown: array<string, int>}
     */
    public function xReport(PosShift $shift): array
    {
        $breakdown = Order::query()
            ->forShift($shift->id)
            ->where('sale_channel', SaleChannel::POS)
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->whereNotNull('payment_status')
            ->selectRaw('payment_status, COUNT(*) as cnt')
            ->groupBy('payment_status')
            ->pluck('cnt', 'payment_status')
            ->all();

        $statusBreakdown = [];
        foreach ($breakdown as $key => $cnt) {
            $status = OrderPaymentStatus::tryFrom((string) $key);
            if ($status === null) {
                continue;
            }
            $statusBreakdown[$status->value] = (int) $cnt;
        }

        return [
            'shift' => $shift,
            'totals' => $this->liveStats($shift),
            'payment_status_breakdown' => $statusBreakdown,
        ];
    }
}
