<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Actions\RecordReturnAction;
use App\Enums\ReturnReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreSupplierReturnRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

final class SupplierReturnController extends Controller
{
    public function __construct(
        private readonly RecordReturnAction $recordReturn,
    ) {}

    public function store(StoreSupplierReturnRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;

        if ($supplier->dealer_id !== $dealerId) {
            abort(404);
        }

        $data = $request->validated();

        try {
            $this->recordReturn->recordSupplierReturn(
                actor: $request->user(),
                dealerId: $dealerId,
                supplier: $supplier,
                lines: $data['items'],
                reason: ReturnReason::from($data['reason']),
                note: $data['note'] ?? null,
                paidCash: $request->paidCash(),
                paidCard: $request->paidCard(),
                cardholderName: $data['cardholder_name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('status', 'Vozvrat yozildi');
    }
}
