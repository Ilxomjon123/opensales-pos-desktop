<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ShopResource;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\CsvExporter;
use App\Services\DebtAgingService;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly DebtAgingService $aging,
        private readonly CsvExporter $exporter,
    ) {}

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;

        $query = Payment::query()
            ->where('dealer_id', $dealerId)
            ->with('shop')
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderByDesc('created_at');

        return $this->exporter->stream(
            filename: 'payments-'.now()->format('Y-m-d').'.csv',
            headers: ['ID', 'Sana', 'Mijoz', 'Tur', 'Summa', 'To\'lov turi', 'Karta egasi', 'Izoh'],
            rowsProvider: function () use ($query): iterable {
                foreach ($query->cursor() as $payment) {
                    yield [
                        $payment->id,
                        $payment->created_at?->format('Y-m-d H:i'),
                        $payment->shop?->name ?? '',
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

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $allowedSorts = ['id', 'amount', 'type', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $payments = Payment::query()
            ->where('dealer_id', $dealerId)
            ->with('shop')
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderBy($sort, $direction)
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Dealer/Finance/Index', [
            'payments' => PaymentResource::collection($payments),
            'shops' => Inertia::defer(fn () => ShopResource::collection(
                Shop::query()->forDealer($dealerId)->get()
            )),
            'paymentTypes' => collect(PaymentType::cases())->map(fn (PaymentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'filters' => $request->only(['shop_id', 'type', 'date_from', 'date_to']),
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    public function aging(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        return Inertia::render('Dealer/Finance/Aging', [
            'report' => $this->aging->report($dealerId),
        ]);
    }

    public function storePayment(StorePaymentRequest $request): RedirectResponse
    {
        $shop = Shop::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->findOrFail($request->integer('shop_id'));

        $type = $request->type();
        $method = $request->method();
        $cardholderName = $type === PaymentType::CREDIT ? $request->input('cardholder_name') : null;

        $type === PaymentType::CREDIT
            ? $this->financeService->credit(
                shop: $shop,
                amount: $request->integer('amount'),
                note: $request->input('note'),
                method: $method,
                cardholderName: $cardholderName,
            )
            : $this->financeService->debit(
                shop: $shop,
                amount: $request->integer('amount'),
                note: $request->input('note'),
            );

        return back()->with('status', 'To\'lov ro\'yxatga olindi');
    }
}
