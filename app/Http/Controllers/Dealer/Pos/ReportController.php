<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Pos;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SaleChannel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('pos.manage'), 403);

        $dealerId = (int) $request->user()->dealer_id;

        $from = $request->date('from') ?? Carbon::now()->startOfDay()->subDays(29);
        $to = $request->date('to') ?? Carbon::now()->endOfDay();

        $base = Order::query()
            ->forDealer($dealerId)
            ->fromPos()
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->whereBetween('created_at', [$from, $to]);

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_cash),0) as cash, COALESCE(SUM(paid_card),0) as card, COALESCE(SUM(debt_amount),0) as debt')
            ->first();

        $byDay = (clone $base)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_cash),0) as cash, COALESCE(SUM(paid_card),0) as card')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $byCashier = Order::query()
            ->join('users', 'users.id', '=', 'orders.cashier_user_id')
            ->where('orders.dealer_id', $dealerId)
            ->where('orders.sale_channel', SaleChannel::POS->value)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('users.id as id, users.name as name, COUNT(*) as cnt, COALESCE(SUM(orders.total),0) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        $byPaymentStatus = (clone $base)
            ->selectRaw('payment_status, COUNT(*) as cnt, COALESCE(SUM(total),0) as total')
            ->groupBy('payment_status')
            ->get();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.dealer_id', $dealerId)
            ->where('orders.sale_channel', SaleChannel::POS->value)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.product_id as product_id, MAX(order_items.product_name) as name, SUM(order_items.qty) as qty, SUM(order_items.qty * order_items.price) as revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        $openShifts = PosShift::query()->forDealer($dealerId)->open()->count();

        return Inertia::render('Dealer/Pos/Reports/Index', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'totals' => [
                'count' => (int) ($totals->cnt ?? 0),
                'total' => (int) ($totals->total ?? 0),
                'cash' => (int) ($totals->cash ?? 0),
                'card' => (int) ($totals->card ?? 0),
                'debt' => (int) ($totals->debt ?? 0),
            ],
            'byDay' => $byDay->map(fn ($r) => [
                'day' => (string) $r->day,
                'count' => (int) $r->cnt,
                'total' => (int) $r->total,
                'cash' => (int) $r->cash,
                'card' => (int) $r->card,
            ])->values(),
            'byCashier' => $byCashier->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'count' => (int) $r->cnt,
                'total' => (int) $r->total,
            ])->values(),
            'byPaymentStatus' => $byPaymentStatus->map(function ($r) {
                $status = $r->payment_status instanceof OrderPaymentStatus
                    ? $r->payment_status
                    : OrderPaymentStatus::from((string) $r->payment_status);

                return [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'count' => (int) $r->cnt,
                    'total' => (int) $r->total,
                ];
            })->values(),
            'topProducts' => $topProducts->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'name' => (string) $r->name,
                'qty' => (float) $r->qty,
                'revenue' => (int) $r->revenue,
            ])->values(),
            'openShifts' => $openShifts,
        ]);
    }
}
