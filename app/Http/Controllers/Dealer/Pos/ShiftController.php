<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Pos\CloseShiftRequest;
use App\Http\Requests\Dealer\Pos\OpenShiftRequest;
use App\Http\Resources\PosShiftResource;
use App\Models\PosShift;
use App\Models\User;
use App\Services\PosShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShiftController extends Controller
{
    public function __construct(
        private readonly PosShiftService $shifts,
    ) {}

    public function index(Request $request): Response
    {
        $request->user()->can('pos.access') ?: abort(403);

        $user = $request->user();
        $dealerId = (int) $user->dealer_id;

        $query = PosShift::query()
            ->forDealer($dealerId)
            ->with('cashier')
            ->latest('opened_at');

        if ($user->isCashier()) {
            $query->forCashier($user->id);
        }

        $shifts = $query->paginate(20)->withQueryString();

        return Inertia::render('Dealer/Pos/Shifts/Index', [
            'shifts' => PosShiftResource::collection($shifts),
            'activeShift' => $this->shifts->getActive($dealerId, $user->id) !== null
                ? PosShiftResource::make($this->shifts->getActive($dealerId, $user->id)->load('cashier'))->resolve()
                : null,
        ]);
    }

    public function show(Request $request, PosShift $shift): Response
    {
        abort_unless($request->user()->can('view', $shift), 403);

        $report = $this->shifts->xReport($shift->load('cashier'));

        return Inertia::render('Dealer/Pos/Shifts/Show', [
            'shift' => PosShiftResource::make($report['shift'])->resolve(),
            'totals' => $report['totals'],
            'breakdown' => $report['payment_status_breakdown'],
        ]);
    }

    public function open(OpenShiftRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $shift = $this->shifts->open(
            cashier: $user,
            openingCash: (int) $request->validated('opening_cash'),
            note: $request->input('opening_note'),
        );

        return redirect()->route('dealer.pos.index')
            ->with('toast', [
                'type' => 'success',
                'message' => "Smena #{$shift->id} ochildi.",
            ]);
    }

    public function close(CloseShiftRequest $request, PosShift $shift): RedirectResponse
    {
        abort_unless($request->user()->can('close', $shift), 403);

        $shift = $this->shifts->close(
            shift: $shift,
            closingCash: (int) $request->validated('closing_cash'),
            note: $request->input('closing_note'),
        );

        return redirect()->route('dealer.pos.shifts.show', $shift)
            ->with('toast', [
                'type' => 'success',
                'message' => "Smena #{$shift->id} yopildi.",
            ]);
    }
}
