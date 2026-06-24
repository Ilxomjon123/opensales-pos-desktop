<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Services\CommissionHistoryService;
use App\Services\PlatformFinanceService;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function __construct(
        private readonly CommissionHistoryService $history,
        private readonly PlatformFinanceService $finance,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Billing/Index', [
            'platformTotals' => $this->finance->totals(),
            'platformMonthly' => $this->history->platformMonthly(),
            'dealers' => Dealer::query()
                ->with('commissionPeriods')
                ->withCount('shops')
                ->orderBy('name')
                ->get()
                ->map(function (Dealer $d): array {
                    $snap = $this->finance->snapshot($d);

                    return [
                        'id' => $d->id,
                        'name' => $d->name,
                        'is_active' => (bool) $d->is_active,
                        'shops_count' => (int) $d->shops_count,
                        'fee_rate' => (float) $d->platform_fee_rate,
                        'commission_type' => $snap['commission_type'],
                        'fixed_commission_amount' => $snap['fixed_commission_amount'],
                        'turnover' => $snap['turnover'],
                        'fee_owed' => $snap['fee_owed'],
                        'total_paid' => $snap['total_paid'],
                        'total_discount' => $snap['total_discount'],
                        'balance' => $snap['balance'],
                    ];
                })
                ->values(),
        ]);
    }

    public function show(Dealer $dealer): Response
    {
        $dealer->loadMissing('commissionPeriods');
        $dealer->loadCount('shops');
        $snap = $this->finance->snapshot($dealer);

        return Inertia::render('Admin/Billing/Dealer', [
            'dealer' => [
                'id' => $dealer->id,
                'name' => $dealer->name,
                'bot_username' => $dealer->bot_username,
                'shops_count' => (int) $dealer->shops_count,
                'fee_rate' => (float) $dealer->platform_fee_rate,
                'commission_type' => $snap['commission_type'],
                'fixed_commission_amount' => $snap['fixed_commission_amount'],
                'is_active' => (bool) $dealer->is_active,
            ],
            'snapshot' => $snap,
            'monthly' => $this->history->dealerMonthly($dealer),
        ]);
    }
}
