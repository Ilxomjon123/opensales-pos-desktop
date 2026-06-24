<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kunlik yopilish (Z-hisobot) — bir kun yoki oraliq uchun:
 * zakaslar, to'lovlar (naqd/karta), kuryer naqdi, qaytarishlar va stok harakatlari.
 *
 * Mavzuviy filter sanasi `created_at` (Sales Report bilan izchillik uchun).
 * Kuryer settlements `settled_at` bo'yicha filterlanadi.
 *
 * @phpstan-type ClosingFilters array{date_from?: string, date_to?: string}
 */
final class DailyClosingService
{
    /**
     * @param  ClosingFilters  $filters
     * @return array<string, mixed>
     */
    public function generate(int $dealerId, array $filters): array
    {
        [$from, $to] = $this->normalizeRange($filters);

        return [
            'meta' => [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
                'is_single_day' => $from->isSameDay($to),
            ],
            'orders' => $this->ordersBlock($dealerId, $from, $to),
            'payments' => $this->paymentsBlock($dealerId, $from, $to),
            'courier_cash' => $this->courierCashBlock($dealerId, $from, $to),
            'returns' => $this->returnsBlock($dealerId, $from, $to),
            'stock' => $this->stockBlock($dealerId, $from, $to),
        ];
    }

    /**
     * @param  ClosingFilters  $filters
     * @return iterable<int, list<string|int|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        $report = $this->generate($dealerId, $filters);

        $meta = $report['meta'];
        yield ['Sana oralig\'i', $meta['date_from'].' — '.$meta['date_to'], null];
        yield ['', '', ''];

        $o = $report['orders'];
        yield ['# BUYURTMALAR', '', ''];
        yield ['Jami', $o['total'], ''];
        yield ['Yetkazilgan', $o['delivered'], ''];
        yield ['Yangi (pending)', $o['pending'], ''];
        yield ['Bekor qilingan', $o['cancelled'], ''];
        yield ['Brutto (yetkazilgan)', $o['gross'], 'so\'m'];
        yield ['Chegirma', $o['discount'], 'so\'m'];
        yield ['Sof', $o['net'], 'so\'m'];
        yield ['', '', ''];

        $p = $report['payments'];
        yield ['# TO\'LOVLAR', '', ''];
        yield ['Naqd kirim', $p['credit_cash'], 'so\'m'];
        yield ['Karta kirim', $p['credit_card'], 'so\'m'];
        yield ['Jami kirim', $p['total_credit'], 'so\'m'];
        yield ['Chiqim (debit)', $p['debit'], 'so\'m'];
        yield ['Sof oqim', $p['net_inflow'], 'so\'m'];
        yield ['', '', ''];

        yield ['# KURYER NAQDI', '', ''];
        yield ['Yetkazib beruvchi', 'Bugun qabul qilindi', 'Bugun topshirildi', 'Joriy qoldiq'];
        foreach ($report['courier_cash'] as $cc) {
            yield [$cc['name'], $cc['received_today'], $cc['settled_today'], $cc['pending_balance']];
        }
        yield ['', '', ''];

        $r = $report['returns'];
        yield ['# QAYTARISHLAR', '', ''];
        yield ['Mijozdan vozvrat (operatsiya)', $r['shop_returns_count'], ''];
        yield ['Ta\'minotchiga vozvrat (operatsiya)', $r['supplier_returns_count'], ''];
        yield ['', '', ''];

        $s = $report['stock'];
        yield ['# STOK HARAKATI', '', ''];
        yield ['Prixod', $s['stock_in_count'], 'operatsiya'];
        yield ['Chiqim', $s['stock_out_count'], 'operatsiya'];
        yield ['Tuzatish', $s['adjust_count'], 'operatsiya'];
    }

    /**
     * @return array{
     *     total: int, pending: int, assembling: int, delivering: int,
     *     delivered: int, received: int, cancelled: int,
     *     gross: int, discount: int, net: int
     * }
     */
    private function ordersBlock(int $dealerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $agg = Order::query()
            ->forDealer($dealerId)
            ->shopChannel()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assembling,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivering,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled,
                COALESCE(SUM(CASE WHEN status IN (?, ?) THEN delivered_total ELSE 0 END), 0) as gross,
                COALESCE(SUM(CASE WHEN status IN (?, ?) THEN discount ELSE 0 END), 0) as discount
            ', [
                OrderStatus::PENDING->value,
                OrderStatus::ASSEMBLING->value,
                OrderStatus::DELIVERING->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::RECEIVED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value,
                OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value,
            ])
            ->first();

        $gross = (int) ($agg->gross ?? 0);
        $discount = (int) ($agg->discount ?? 0);

        return [
            'total' => (int) ($agg->total ?? 0),
            'pending' => (int) ($agg->pending ?? 0),
            'assembling' => (int) ($agg->assembling ?? 0),
            'delivering' => (int) ($agg->delivering ?? 0),
            'delivered' => (int) ($agg->delivered ?? 0) + (int) ($agg->received ?? 0),
            'received' => (int) ($agg->received ?? 0),
            'cancelled' => (int) ($agg->cancelled ?? 0),
            'gross' => $gross,
            'discount' => $discount,
            'net' => $gross - $discount,
        ];
    }

    /**
     * @return array{
     *     credit_cash: int, credit_card: int, debit: int,
     *     total_credit: int, net_inflow: int
     * }
     */
    private function paymentsBlock(int $dealerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $agg = Payment::query()
            ->where('dealer_id', $dealerId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = ? AND method = ? THEN amount ELSE 0 END), 0) as credit_cash,
                COALESCE(SUM(CASE WHEN type = ? AND method = ? THEN amount ELSE 0 END), 0) as credit_card,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as debit
            ', [
                PaymentType::CREDIT->value, PaymentMethod::CASH->value,
                PaymentType::CREDIT->value, PaymentMethod::CARD->value,
                PaymentType::DEBIT->value,
            ])
            ->first();

        $cash = (int) ($agg->credit_cash ?? 0);
        $card = (int) ($agg->credit_card ?? 0);
        $debit = (int) ($agg->debit ?? 0);
        $totalCredit = $cash + $card;

        return [
            'credit_cash' => $cash,
            'credit_card' => $card,
            'debit' => $debit,
            'total_credit' => $totalCredit,
            'net_inflow' => $totalCredit - $debit,
        ];
    }

    /**
     * @return list<array{
     *     deliveryman_id: int, name: string,
     *     received_today: int, settled_today: int, pending_balance: int
     * }>
     */
    private function courierCashBlock(int $dealerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Kuryerlar ro'yxati — har biri uchun bugungi qabul + topshirish + joriy qoldiq.
        $couriers = User::query()
            ->where('dealer_id', $dealerId)
            ->where('role', UserRole::DELIVERYMAN)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($couriers->isEmpty()) {
            return [];
        }

        $courierIds = $couriers->pluck('id')->all();

        $received = DB::table('payments')
            ->where('dealer_id', $dealerId)
            ->where('method', PaymentMethod::CASH->value)
            ->where('type', PaymentType::CREDIT->value)
            ->whereIn('deliveryman_id', $courierIds)
            ->whereBetween('created_at', [$from, $to])
            ->select('deliveryman_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('deliveryman_id')
            ->pluck('total', 'deliveryman_id');

        $settled = DB::table('courier_settlements')
            ->where('dealer_id', $dealerId)
            ->whereIn('deliveryman_id', $courierIds)
            ->whereBetween('settled_at', [$from, $to])
            ->select('deliveryman_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('deliveryman_id')
            ->pluck('total', 'deliveryman_id');

        // Joriy qoldiq = barcha vaqtdagi qabul - barcha vaqtdagi topshirish.
        $receivedAll = DB::table('payments')
            ->where('dealer_id', $dealerId)
            ->where('method', PaymentMethod::CASH->value)
            ->where('type', PaymentType::CREDIT->value)
            ->whereIn('deliveryman_id', $courierIds)
            ->select('deliveryman_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('deliveryman_id')
            ->pluck('total', 'deliveryman_id');

        $settledAll = DB::table('courier_settlements')
            ->where('dealer_id', $dealerId)
            ->whereIn('deliveryman_id', $courierIds)
            ->select('deliveryman_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('deliveryman_id')
            ->pluck('total', 'deliveryman_id');

        return $couriers->map(function (User $c) use ($received, $settled, $receivedAll, $settledAll): array {
            return [
                'deliveryman_id' => $c->id,
                'name' => (string) $c->name,
                'received_today' => (int) ($received[$c->id] ?? 0),
                'settled_today' => (int) ($settled[$c->id] ?? 0),
                'pending_balance' => (int) ($receivedAll[$c->id] ?? 0) - (int) ($settledAll[$c->id] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return array{shop_returns_count: int, supplier_returns_count: int}
     */
    private function returnsBlock(int $dealerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Transaction::query()
            ->forDealer($dealerId)
            ->whereIn('type', [TransactionType::SHOP_RETURN, TransactionType::SUPPLIER_RETURN])
            ->whereBetween('created_at', [$from, $to])
            ->select('type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return [
            'shop_returns_count' => (int) ($rows[TransactionType::SHOP_RETURN->value] ?? 0),
            'supplier_returns_count' => (int) ($rows[TransactionType::SUPPLIER_RETURN->value] ?? 0),
        ];
    }

    /**
     * @return array{stock_in_count: int, stock_out_count: int, adjust_count: int}
     */
    private function stockBlock(int $dealerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Transaction::query()
            ->forDealer($dealerId)
            ->whereIn('type', [TransactionType::STOCK_IN, TransactionType::STOCK_OUT, TransactionType::STOCK_ADJUST])
            ->whereBetween('created_at', [$from, $to])
            ->select('type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return [
            'stock_in_count' => (int) ($rows[TransactionType::STOCK_IN->value] ?? 0),
            'stock_out_count' => (int) ($rows[TransactionType::STOCK_OUT->value] ?? 0),
            'adjust_count' => (int) ($rows[TransactionType::STOCK_ADJUST->value] ?? 0),
        ];
    }

    /**
     * @param  ClosingFilters  $filters
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function normalizeRange(array $filters): array
    {
        $from = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $to = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : $from->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }
}
