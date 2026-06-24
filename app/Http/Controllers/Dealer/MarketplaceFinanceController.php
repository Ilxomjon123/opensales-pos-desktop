<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreMarketplacePaymentRequest;
use App\Models\Dealer;
use App\Models\MarketplaceBalance;
use App\Services\MarketplaceFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Birja moliyasi — dillerlararo hamkor balanslari va to'lovlar.
 * `balance` joriy diller nuqtai nazaridan: musbat = hamkor menga qarzdor.
 */
final class MarketplaceFinanceController extends Controller
{
    public function __construct(private readonly MarketplaceFinanceService $finance) {}

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $balances = MarketplaceBalance::query()
            ->where('dealer_id', $dealerId)
            ->where('balance', '!=', 0)
            ->with('partner:id,name,contact_phone')
            ->orderByDesc('balance')
            ->get()
            ->map(fn (MarketplaceBalance $b): array => [
                'partner_id' => $b->partner_dealer_id,
                'partner_name' => $b->partner?->name,
                'partner_phone' => $b->partner?->contact_phone,
                'balance' => (int) $b->balance,
            ]);

        return Inertia::render('Dealer/Marketplace/Finance', [
            'balances' => $balances,
            'totals' => [
                'receivable' => (int) $balances->where('balance', '>', 0)->sum('balance'),
                'payable' => (int) abs($balances->where('balance', '<', 0)->sum('balance')),
            ],
            'paymentMethods' => array_map(
                static fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()],
                PaymentMethod::cases(),
            ),
        ]);
    }

    /**
     * Joriy diller (sotuvchi) hamkordan (xaridor) tushgan to'lovni yozadi — qarz kamayadi.
     */
    public function storePayment(StoreMarketplacePaymentRequest $request): RedirectResponse
    {
        $seller = Dealer::query()->findOrFail($request->user()->dealer_id);
        $buyer = Dealer::query()->findOrFail($request->integer('partner_dealer_id'));

        $this->finance->credit(
            seller: $seller,
            buyer: $buyer,
            amount: $request->integer('amount'),
            note: $request->input('note'),
            method: $request->enum('method', PaymentMethod::class),
            cardholderName: $request->input('cardholder_name'),
        );

        return back()->with('status', 'To\'lov ro\'yxatga olindi');
    }
}
