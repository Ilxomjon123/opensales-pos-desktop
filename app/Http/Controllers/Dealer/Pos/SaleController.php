<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Pos;

use App\Actions\CreatePosSaleAction;
use App\Enums\OrderPaymentStatus;
use App\Enums\SaleChannel;
use App\Enums\ShopType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Pos\StorePosSaleRequest;
use App\Http\Resources\PosSaleResource;
use App\Http\Resources\PosShiftResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\PosShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SaleController extends Controller
{
    public function __construct(
        private readonly PosShiftService $shifts,
        private readonly CreatePosSaleAction $createSale,
    ) {}

    /**
     * Kassir terminal — POS Index.
     */
    public function index(Request $request): Response
    {
        $request->user()->can('pos.access') ?: abort(403);

        $user = $request->user();
        $dealerId = (int) $user->dealer_id;

        $activeShift = $this->shifts->getActive($dealerId, $user->id);

        $products = Product::query()
            ->forDealer($dealerId)
            ->active()
            ->with(['category', 'images', 'types' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->limit(500)
            ->get();

        $categories = ProductCategory::query()
            ->forDealer($dealerId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $customers = Shop::query()
            ->forDealer($dealerId)
            ->active()
            ->orderByRaw("CASE type WHEN 'walk_in' THEN 0 WHEN 'individual' THEN 1 WHEN 'telegram' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'type', 'balance']);

        return Inertia::render('Dealer/Pos/Index', [
            'activeShift' => $activeShift !== null
                ? array_merge(
                    PosShiftResource::make($activeShift->load('cashier'))->resolve(),
                    ['live_totals' => $this->shifts->liveStats($activeShift)],
                )
                : null,
            'products' => ProductResource::collection($products),
            'categories' => $categories,
            'customers' => $customers->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'type' => $s->type?->value,
                'balance' => (int) $s->balance,
                'is_walk_in' => $s->type === ShopType::WALK_IN,
            ])->values(),
            'lastSale' => fn () => session('lastSale'),
        ]);
    }

    public function store(StorePosSaleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $shift = $this->shifts->getActive((int) $user->dealer_id, $user->id);

        if ($shift === null) {
            return back()->withErrors(['shift' => 'Avval smenani oching.']);
        }

        /** @var Shop $customer */
        $customer = Shop::query()
            ->forDealer((int) $user->dealer_id)
            ->where('id', (int) $request->validated('customer_id'))
            ->firstOrFail();

        $sale = $this->createSale->execute(
            shift: $shift,
            cashier: $user,
            customer: $customer,
            items: $request->validated('items'),
            paidCash: (int) $request->validated('paid_cash'),
            paidCard: (int) $request->validated('paid_card'),
            discount: (int) ($request->validated('discount') ?? 0),
            cardholderName: $request->input('cardholder_name'),
            note: $request->input('note'),
        );

        $payload = PosSaleResource::make(
            $sale->loadMissing(['shop', 'cashier', 'shift', 'items'])
        )->resolve();

        return redirect()->route('dealer.pos.index')
            ->with('lastSale', $payload)
            ->with('toast', [
                'type' => 'success',
                'message' => "Chek #{$sale->receipt_number} yaratildi.",
            ]);
    }

    /**
     * Sotuvlar tarixi (paginated).
     */
    public function listing(Request $request): Response
    {
        $request->user()->can('pos.access') ?: abort(403);

        $user = $request->user();
        $dealerId = (int) $user->dealer_id;

        $query = Order::query()
            ->forDealer($dealerId)
            ->fromPos()
            ->with(['shop', 'cashier', 'shift', 'items']);

        if ($user->isCashier()) {
            $query->where('cashier_user_id', $user->id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', (int) $request->input('shift_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('receipt_number', 'like', $term)
                ->orWhereHas('shop', fn ($q2) => $q2->where('name', 'like', $term))
            );
        }

        if ($request->filled('product_id')) {
            $productId = (int) $request->input('product_id');
            $query->whereHas('items', fn ($q) => $q->where('product_id', $productId));
        }

        $query
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')));

        $sales = $query->latest('id')->paginate(25)->withQueryString();

        $products = Product::query()
            ->forDealer($dealerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dealer/Pos/Sales/Index', [
            'sales' => PosSaleResource::collection($sales),
            'products' => $products,
            'paymentStatuses' => array_map(static fn (OrderPaymentStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ], OrderPaymentStatus::cases()),
            'filters' => [
                'payment_status' => $request->input('payment_status'),
                'shift_id' => $request->input('shift_id'),
                'search' => $request->input('search'),
                'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);
    }

    public function show(Request $request, Order $sale): Response
    {
        abort_unless($sale->sale_channel === SaleChannel::POS, 404);
        abort_unless($request->user()->dealer_id === $sale->dealer_id, 403);
        if ($request->user()->isCashier() && $sale->cashier_user_id !== $request->user()->id) {
            abort(403);
        }

        $sale->load(['shop', 'cashier', 'shift', 'items.product', 'items.productType']);

        return Inertia::render('Dealer/Pos/Sales/Show', [
            'sale' => PosSaleResource::make($sale)->resolve(),
        ]);
    }
}
