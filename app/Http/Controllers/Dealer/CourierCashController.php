<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreCourierSettlementRequest;
use App\Http\Resources\CourierSettlementResource;
use App\Models\User;
use App\Services\CourierCashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CourierCashController extends Controller
{
    public function __construct(private readonly CourierCashService $courierCash) {}

    /**
     * Yetkazib beruvchilar va ularning qo'lidagi naqd qoldiqlari.
     * Faqat diller egasi ko'radi. Yetkazib beruvchi bo'lsa
     * o'zining show sahifasiga redirect bo'ladi.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isDeliveryman()) {
            return redirect()->route('dealer.courier-cash.show', $user);
        }

        abort_unless($user->isOwner(), 403);

        $dealerId = (int) $user->dealer_id;
        $balances = $this->courierCash->balancesForDealer($dealerId);

        $deliverymen = User::query()
            ->deliverymenFor($dealerId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $rows = $deliverymen->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'phone' => $u->phone,
            'balance' => (int) ($balances[$u->id] ?? 0),
        ])->all();

        $totalBalance = array_sum(array_column($rows, 'balance'));

        return Inertia::render('Dealer/CourierCash/Index', [
            'rows' => $rows,
            'summary' => [
                'deliverymen' => count($rows),
                'total_balance' => $totalBalance,
                'with_balance' => count(array_filter($rows, fn ($r) => $r['balance'] > 0)),
            ],
        ]);
    }

    /**
     * Bir yetkazib beruvchining naqd to'lovlari va topshirish tarixi.
     * Owner — istalgan yetkazib beruvchini ko'radi.
     * Deliveryman — faqat o'zini.
     */
    public function show(Request $request, User $deliveryman): Response
    {
        $user = $request->user();

        $sameDealer = $deliveryman->isDeliveryman()
            && (int) $deliveryman->dealer_id === (int) $user->dealer_id;

        abort_unless($sameDealer, 404);

        $canSettle = $user->isOwner();
        $isSelf = $user->isDeliveryman() && $user->id === $deliveryman->id;

        abort_unless($canSettle || $isSelf, 403);

        $totals = $this->courierCash->totalsFor($deliveryman);
        $payments = $this->courierCash->recentCashPayments($deliveryman, 100);
        $settlements = $this->courierCash->settlementHistory($deliveryman);

        return Inertia::render('Dealer/CourierCash/Show', [
            'deliveryman' => [
                'id' => $deliveryman->id,
                'name' => $deliveryman->name,
                'phone' => $deliveryman->phone,
            ],
            'totals' => $totals,
            'payments' => $payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (int) $p->amount,
                'note' => $p->note,
                'created_at' => $p->created_at?->toIso8601String(),
                'shop' => $p->shop ? [
                    'id' => $p->shop->id,
                    'name' => $p->shop->name,
                    'phone' => $p->shop->phone,
                ] : null,
                'order' => $p->order ? [
                    'id' => $p->order->id,
                    'number' => $p->order->displayNumber(),
                ] : null,
            ]),
            'settlements' => CourierSettlementResource::collection($settlements),
            'can_settle' => $canSettle,
        ]);
    }

    /**
     * Yetkazib beruvchidan naqd pulni qabul qilish (topshirish).
     */
    public function store(StoreCourierSettlementRequest $request, User $deliveryman): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $deliveryman->isDeliveryman() && (int) $deliveryman->dealer_id === (int) $user->dealer_id,
            404,
        );

        $this->courierCash->settle(
            deliveryman: $deliveryman,
            amount: $request->integer('amount'),
            by: $user,
            note: $request->input('note'),
        );

        return back()->with('status', 'Naqd pul qabul qilindi');
    }
}
