<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Pos;

use App\Enums\ShopType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Pos\StorePosCustomerPaymentRequest;
use App\Http\Requests\Dealer\Pos\StorePosCustomerRequest;
use App\Http\Resources\PosSaleResource;
use App\Models\Order;
use App\Models\Shop;
use App\Services\FinanceService;
use App\Services\PosShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly PosShiftService $shifts,
    ) {}

    public function index(Request $request): Response
    {
        $request->user()->can('pos.access') ?: abort(403);
        $dealerId = (int) $request->user()->dealer_id;

        $query = Shop::query()
            ->forDealer($dealerId)
            ->posCustomers()
            ->withCount('orders')
            ->orderBy('type')
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        if ($request->filled('only_debt')) {
            $query->where('balance', '<', 0);
        }

        $customers = $query->paginate(25)->withQueryString()->through(fn (Shop $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'type' => $s->type?->value,
            'type_label' => $s->type?->label(),
            'balance' => (int) $s->balance,
            'orders_count' => (int) $s->orders_count,
            'is_active' => (bool) $s->is_active,
        ]);

        return Inertia::render('Dealer/Pos/Customers/Index', [
            'customers' => [
                'data' => $customers->items(),
                'links' => [
                    'first' => $customers->url(1),
                    'last' => $customers->url($customers->lastPage()),
                    'prev' => $customers->previousPageUrl(),
                    'next' => $customers->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $request->input('search'),
                'only_debt' => (bool) $request->input('only_debt', false),
            ],
        ]);
    }

    public function store(StorePosCustomerRequest $request): RedirectResponse
    {
        $dealerId = (int) $request->user()->dealer_id;

        $customer = Shop::query()->create([
            'dealer_id' => $dealerId,
            'type' => ShopType::INDIVIDUAL,
            'name' => $request->validated('name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'is_active' => true,
            'balance' => 0,
        ]);

        return redirect()->route('dealer.pos.customers.show', $customer)
            ->with('toast', ['type' => 'success', 'message' => 'Mijoz qo\'shildi.']);
    }

    public function update(StorePosCustomerRequest $request, Shop $customer): RedirectResponse
    {
        abort_unless($request->user()->dealer_id === $customer->dealer_id, 403);
        abort_unless($customer->type === ShopType::INDIVIDUAL, 422);

        $customer->update([
            'name' => $request->validated('name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Mijoz yangilandi.']);
    }

    public function show(Request $request, Shop $customer): Response
    {
        abort_unless($request->user()->dealer_id === $customer->dealer_id, 403);
        abort_unless($customer->isPosCustomer(), 404);

        $sales = Order::query()
            ->forShop($customer->id)
            ->fromPos()
            ->with(['items', 'cashier', 'shift'])
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('Dealer/Pos/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'type' => $customer->type?->value,
                'type_label' => $customer->type?->label(),
                'balance' => (int) $customer->balance,
                'is_active' => (bool) $customer->is_active,
            ],
            'sales' => PosSaleResource::collection($sales),
        ]);
    }

    public function recordPayment(StorePosCustomerPaymentRequest $request, Shop $customer): RedirectResponse
    {
        abort_unless($request->user()->dealer_id === $customer->dealer_id, 403);
        abort_unless($customer->type === ShopType::INDIVIDUAL, 422);

        $shift = $this->shifts->getActive((int) $request->user()->dealer_id, $request->user()->id);

        $this->finance->credit(
            shop: $customer,
            amount: (int) $request->validated('amount'),
            note: $request->input('note') ?? 'POS qarz to\'lovi',
            method: $request->method(),
            cardholderName: $request->input('cardholder_name'),
            shiftId: $shift?->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'To\'lov yozildi.']);
    }

    /**
     * Inline lookup (kassir UI'da telefon orqali mijoz qidirish uchun JSON).
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->user()->can('pos.access') ?: abort(403);
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $dealerId = (int) $request->user()->dealer_id;
        $like = '%'.$term.'%';

        $rows = Shop::query()
            ->forDealer($dealerId)
            ->active()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like))
            ->limit(15)
            ->get(['id', 'name', 'phone', 'type', 'balance']);

        return response()->json([
            'data' => $rows->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'type' => $s->type?->value,
                'balance' => (int) $s->balance,
            ])->values(),
        ]);
    }
}
