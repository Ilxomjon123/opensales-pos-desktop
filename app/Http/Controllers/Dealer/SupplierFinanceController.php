<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CsvExporter;
use App\Services\SupplierFinanceService;
use App\Support\Translit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SupplierFinanceController extends Controller
{
    public function __construct(
        private readonly SupplierFinanceService $supplierFinance,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $date = $this->resolveDate($request->string('date')->toString());
        $cutoff = $date->endOfDay();

        $allowedSorts = ['name', 'balance'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $search = trim($request->string('search')->toString());

        $balanceSub = SupplierPayment::query()
            ->selectRaw("supplier_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) AS historical_balance")
            ->where('dealer_id', $dealerId)
            ->where('created_at', '<=', $cutoff)
            ->groupBy('supplier_id');

        $suppliers = Supplier::query()
            ->forDealer($dealerId)
            ->select('suppliers.id', 'suppliers.name', 'suppliers.phone', 'suppliers.contact_person', 'suppliers.is_active')
            ->selectRaw('COALESCE(p.historical_balance, 0) AS balance')
            ->leftJoinSub($balanceSub, 'p', 'p.supplier_id', '=', 'suppliers.id')
            ->when($search !== '', fn ($q) => Translit::applyLike($q, ['suppliers.name'], $search))
            ->orderBy($sort, $direction)
            ->orderBy('suppliers.id')
            ->paginate(50)
            ->withQueryString();

        $rows = collect($suppliers->items())->map(fn ($supplier) => [
            'id' => (int) $supplier->id,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'contact_person' => $supplier->contact_person,
            'is_active' => (bool) $supplier->is_active,
            'balance' => (int) $supplier->balance,
        ])->all();

        $totals = SupplierPayment::query()
            ->where('dealer_id', $dealerId)
            ->where('created_at', '<=', $cutoff)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) AS net,
                COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS credits,
                COALESCE(SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END), 0) AS debits
            ")
            ->first();

        return Inertia::render('Dealer/SuppliersBalance/Index', [
            'suppliers' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'last_page' => $suppliers->lastPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                    'from' => $suppliers->firstItem(),
                    'to' => $suppliers->lastItem(),
                ],
                'links' => [
                    'first' => $suppliers->url(1),
                    'last' => $suppliers->url($suppliers->lastPage()),
                    'prev' => $suppliers->previousPageUrl(),
                    'next' => $suppliers->nextPageUrl(),
                ],
            ],
            'totals' => [
                'net' => (int) ($totals->net ?? 0),
                'credits' => (int) ($totals->credits ?? 0),
                'debits' => (int) ($totals->debits ?? 0),
            ],
            'filters' => [
                'date' => $date->toDateString(),
                'search' => $search !== '' ? $search : null,
            ],
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    public function payments(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $allowedSorts = ['id', 'amount', 'type', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $payments = SupplierPayment::query()
            ->where('dealer_id', $dealerId)
            ->with(['supplier', 'transaction:id,type'])
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderBy($sort, $direction)
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Dealer/SuppliersBalance/Payments', [
            'payments' => SupplierPaymentResource::collection($payments),
            'suppliers' => Inertia::defer(fn () => ['data' => $this->suppliersWithBalance($dealerId)]),
            'paymentTypes' => collect(PaymentType::cases())->map(fn (PaymentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'filters' => $request->only(['supplier_id', 'type', 'date_from', 'date_to']),
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    public function exportPayments(Request $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;

        $query = SupplierPayment::query()
            ->where('dealer_id', $dealerId)
            ->with('supplier')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderByDesc('created_at');

        return $this->exporter->stream(
            filename: 'supplier-payments-'.now()->format('Y-m-d').'.csv',
            headers: ['ID', 'Sana', "Ta'minotchi", 'Tur', 'Summa', "To'lov turi", 'Karta egasi', 'Izoh'],
            rowsProvider: function () use ($query): iterable {
                foreach ($query->cursor() as $payment) {
                    yield [
                        $payment->id,
                        $payment->created_at?->format('Y-m-d H:i'),
                        $payment->supplier?->name ?? '',
                        $payment->type->label(),
                        $payment->amount,
                        $payment->method->label(),
                        $payment->cardholder_name ?? '',
                        $payment->note ?? '',
                    ];
                }
            },
        );
    }

    /**
     * @return array<int, array{id: int, name: string, balance: int}>
     */
    private function suppliersWithBalance(int $dealerId): array
    {
        $balanceSub = SupplierPayment::query()
            ->selectRaw("supplier_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) AS historical_balance")
            ->where('dealer_id', $dealerId)
            ->groupBy('supplier_id');

        return Supplier::query()
            ->forDealer($dealerId)
            ->select('suppliers.id', 'suppliers.name')
            ->selectRaw('COALESCE(p.historical_balance, 0) AS balance')
            ->leftJoinSub($balanceSub, 'p', 'p.supplier_id', '=', 'suppliers.id')
            ->orderBy('suppliers.name')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'balance' => (int) $s->balance,
            ])
            ->all();
    }

    public function storePayment(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->findOrFail($request->integer('supplier_id'));

        $this->authorize('pay', $supplier);

        $type = $request->type();
        $method = $request->method();
        $cardholderName = $type === PaymentType::CREDIT ? $request->input('cardholder_name') : null;

        $type === PaymentType::CREDIT
            ? $this->supplierFinance->credit(
                supplier: $supplier,
                amount: $request->integer('amount'),
                note: $request->input('note'),
                method: $method,
                cardholderName: $cardholderName,
            )
            : $this->supplierFinance->debit(
                supplier: $supplier,
                amount: $request->integer('amount'),
                note: $request->input('note'),
            );

        return back()->with('status', 'To\'lov ro\'yxatga olindi');
    }

    private function resolveDate(string $raw): CarbonImmutable
    {
        if ($raw === '') {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }
}
