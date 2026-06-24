<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\AcceptReturnRequest;
use App\Http\Requests\Dealer\AssembleOrderRequest;
use App\Http\Requests\Dealer\AssignDeliverymanRequest;
use App\Http\Requests\Dealer\CancelOrderRequest;
use App\Http\Requests\Dealer\CreateOrderRequest;
use App\Http\Requests\Dealer\DeliverOrderRequest;
use App\Http\Requests\Dealer\DispatchOrderRequest;
use App\Http\Requests\Dealer\EditOrderRequest;
use App\Http\Requests\Dealer\StoreOrderMessageRequest;
use App\Http\Requests\Dealer\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopResource;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\OrderMessageService;
use App\Services\OrderService;
use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CsvExporter $exporter,
        private readonly OrderMessageService $orderMessages,
    ) {}

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;

        $query = Order::query()
            ->forDealer($dealerId)
            ->with('shop')
            ->when($request->filled('status'), function ($q) use ($request) {
                $statuses = array_values(array_filter(
                    (array) $request->input('status'),
                    fn ($s) => $s !== null && $s !== '',
                ));

                if ($statuses !== []) {
                    $q->whereIn('status', $statuses);
                }
            })
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderByDesc('created_at');

        return $this->exporter->stream(
            filename: 'orders-'.now()->format('Y-m-d').'.csv',
            headers: [
                'ID', 'Sana', 'Mijoz', 'Holat', 'Jami', 'To\'langan',
                'Yetkazildi', 'Yetkazilgan sana', 'Izoh',
            ],
            rowsProvider: function () use ($query): iterable {
                foreach ($query->cursor() as $order) {
                    yield [
                        $order->id,
                        $order->created_at?->format('Y-m-d H:i'),
                        $order->shop?->name ?? '',
                        $order->status->label(),
                        $order->total,
                        $order->paid_amount ?? 0,
                        $order->delivered_total ?? 0,
                        $order->delivered_at?->format('Y-m-d H:i') ?? '',
                        $order->note ?? '',
                    ];
                }
            },
        );
    }

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $allowedSorts = ['id', 'total', 'status', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $orders = Order::query()
            ->forDealer($dealerId)
            ->with(['shop', 'buyerDealer:id,name,contact_phone', 'deliveryman:id,name,phone', 'items'])
            ->withPendingReturn()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));
                // Buyurtma raqami — ko'rsatiladigan oylik raqam (month_number) yoki
                // eski global raqam (number) bo'yicha. Faqat sof raqamli qiymat qabul qilamiz.
                $value = ctype_digit($term) ? (int) $term : -1;
                $q->where(function ($qq) use ($value): void {
                    $qq->where('month_number', $value)->orWhere('number', $value);
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $statuses = array_values(array_filter(
                    (array) $request->input('status'),
                    fn ($s) => $s !== null && $s !== '',
                ));

                if ($statuses !== []) {
                    $q->whereIn('status', $statuses);
                }
            })
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('deliveryman_id'), fn ($q) => $q->where('deliveryman_id', $request->integer('deliveryman_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dealer/Orders/Index', [
            'orders' => OrderResource::collection($orders),
            // Filtr dropdown'i — sahifani to'sib qo'ymaslik uchun fonda yuklanadi
            'shops' => Inertia::defer(fn () => ShopResource::collection(
                Shop::query()->forDealer($dealerId)->active()->get()
            )),
            'statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => $request->only(['search', 'status', 'shop_id', 'date_from', 'date_to']),
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Order::class);

        $dealerId = (int) $request->user()->dealer_id;

        $shops = Shop::query()
            ->forDealer($dealerId)
            ->active()
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->forDealer($dealerId)
            ->active()
            ->with(['images', 'types' => fn ($q) => $q->where('is_active', true), 'types.images'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Dealer/Orders/Create', [
            'shops' => ShopResource::collection($shops),
            'products' => ProductResource::collection($products),
            'preselectedShopId' => $request->integer('shop_id') ?: null,
        ]);
    }

    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $dealerId = (int) $request->user()->dealer_id;

        $shop = Shop::query()
            ->forDealer($dealerId)
            ->findOrFail((int) $request->validated('shop_id'));

        $cart = $this->buildCartFromRequest($dealerId, (array) $request->validated('items'));

        $order = $this->orderService->createFromCart(
            shop: $shop,
            cart: $cart,
            note: $request->validated('note'),
            channel: OrderChannel::MANUAL,
        );

        return redirect()
            ->route('dealer.orders.show', $order)
            ->with('status', "Buyurtma #{$order->displayNumber()} yaratildi");
    }

    /**
     * @param  array<int, array{product_id: int|string, product_type_id?: int|string|null, qty: int|string, pack_qty?: int|string|null, price?: int|string|null}>  $items
     */
    private function buildCartFromRequest(int $dealerId, array $items): Cart
    {
        $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();
        $typeIds = collect($items)->pluck('product_type_id')->filter()->unique()->all();

        $products = $productIds === []
            ? collect()
            : Product::query()
                ->whereIn('id', $productIds)
                ->where('dealer_id', $dealerId)
                ->get()
                ->keyBy('id');

        $types = $typeIds === []
            ? collect()
            : ProductType::query()
                ->whereIn('id', $typeIds)
                ->get()
                ->keyBy('id');

        $cart = new Cart;

        foreach ($items as $row) {
            $productId = (int) $row['product_id'];
            $typeId = isset($row['product_type_id']) && (int) $row['product_type_id'] > 0
                ? (int) $row['product_type_id']
                : null;
            $qty = (float) $row['qty'];
            $packQty = isset($row['pack_qty']) && $row['pack_qty'] !== '' && $row['pack_qty'] !== null
                ? (int) $row['pack_qty']
                : null;

            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $type = $typeId !== null ? $types->get($typeId) : null;

            $price = isset($row['price']) && $row['price'] !== '' && $row['price'] !== null
                ? (float) $row['price']
                : (float) ($type?->price ?? $product->price);
            $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));
            $bulkOnly = (bool) ($type?->bulk_only ?? $product->bulk_only);
            $unit = $product->unit->value;

            $packPriceInput = $row['pack_price'] ?? null;
            $packPrice = $packPriceInput !== null && $packPriceInput !== ''
                ? (float) $packPriceInput
                : ($type?->pack_price !== null
                    ? (float) $type->pack_price
                    : ($product->pack_price !== null ? (float) $product->pack_price : null));

            if ($packPrice === null && $packSize > 1) {
                $packPrice = round($price * $packSize, 2);
            }

            $cart = $cart->add(new CartItem(
                productId: $product->id,
                productName: $product->name,
                price: $price,
                qty: $qty,
                unit: $unit,
                packSize: $packSize,
                packQty: $packQty,
                bulkOnly: $bulkOnly,
                productTypeId: $type?->id,
                productTypeName: $type?->name,
                productTypeCode: null,
                packPrice: $packPrice,
            ));
        }

        return $cart;
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'shop',
            'buyerDealer',
            'items.product',
            'items.productType',
            'deliveryman',
            'cancelledBy',
            'statusHistory.user',
            'statusHistory.member',
            'messages.author',
        ]);

        $dealerId = (int) $order->dealer_id;
        $user = request()->user();

        $deliverymen = $user->isOwner() || $user->isWarehouse()
            ? User::query()
                ->where('dealer_id', $dealerId)
                ->where('role', UserRole::DELIVERYMAN)
                ->orderBy('name')
                ->get(['id', 'name', 'phone'])
            : collect();

        return Inertia::render('Dealer/Orders/Show', [
            'order' => OrderResource::make($order),
            'availableTransitions' => collect(OrderStatus::cases())
                ->filter(fn (OrderStatus $s) => $order->status->canTransitionTo($s))
                ->map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'abilities' => [
                'assemble' => $user->can('assemble', $order),
                'editPicked' => $user->can('editPicked', $order),
                'dispatch' => $user->can('dispatch', $order),
                'deliver' => $user->can('deliver', $order),
                'cancel' => $user->can('cancel', $order),
                'assignDeliveryman' => $user->can('assignDeliveryman', $order),
                'selfAssign' => $user->can('selfAssign', $order),
                'releaseSelf' => $user->can('releaseSelf', $order),
                'acceptReturn' => $user->can('acceptReturn', $order),
            ],
            'deliverymen' => $deliverymen,
            // Faqat "Yetkazish" modali ochilganda router.reload({ only: ['dealerProducts'] })
            // chaqiriladi — har show da MB li katalogni yuklamaslik uchun
            'dealerProducts' => Inertia::optional(fn () => ProductResource::collection(
                Product::query()
                    ->forDealer($dealerId)
                    ->active()
                    ->with(['images', 'types' => fn ($q) => $q->where('is_active', true), 'types.images'])
                    ->orderBy('name')
                    ->get()
            )),
        ]);
    }

    public function storeMessage(StoreOrderMessageRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderMessages->create($order, $request->user(), $request->validated('body'));

        return back()->with('status', 'Xabar yuborildi');
    }

    public function updateMessage(StoreOrderMessageRequest $request, Order $order, OrderMessage $message): RedirectResponse
    {
        $this->authorize('view', $order);
        abort_unless($message->order_id === $order->id, 404);

        $this->orderMessages->update($message, $request->validated('body'));

        return back()->with('status', 'Xabar tahrirlandi');
    }

    public function destroyMessage(Order $order, OrderMessage $message): RedirectResponse
    {
        $this->authorize('view', $order);
        abort_unless($message->order_id === $order->id, 404);

        $this->orderMessages->delete($message);

        return back()->with('status', "Xabar o'chirildi");
    }

    public function update(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $next = $request->status();
        $reason = $next === OrderStatus::CANCELLED
            ? (string) $request->input('cancellation_reason')
            : null;

        $this->orderService->transition($order, $next, $request->user(), $reason);

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} holati yangilandi");
    }

    public function assemble(AssembleOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('assemble', $order);

        $this->orderService->assemble(
            order: $order,
            by: $request->user(),
            pickedItems: (array) $request->validated('items', []),
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} tayyorlandi");
    }

    public function editPicked(AssembleOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('editPicked', $order);

        $this->orderService->editPicked(
            order: $order,
            items: (array) $request->validated('items', []),
            by: $request->user(),
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} skladdan berilgan miqdori tahrirlandi");
    }

    public function dispatchToDelivery(DispatchOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('dispatch', $order);

        $deliverymanId = $request->validated('deliveryman_id');

        if ($deliverymanId !== null) {
            $this->authorize('assignDeliveryman', $order);
        }

        $this->orderService->dispatch(
            order: $order,
            by: $request->user(),
            deliverymanId: $deliverymanId !== null ? (int) $deliverymanId : null,
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} yo'lda");
    }

    public function acceptReturn(AcceptReturnRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('acceptReturn', $order);

        $this->orderService->acceptReturn(
            order: $order,
            items: (array) $request->validated('items'),
            by: $request->user(),
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} vozvrati qabul qilindi");
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $this->orderService->cancel(
            order: $order,
            by: $request->user(),
            reason: (string) $request->validated('reason'),
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} bekor qilindi");
    }

    public function assignDeliveryman(AssignDeliverymanRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('assignDeliveryman', $order);

        $deliverymanId = $request->validated('deliveryman_id');

        $this->orderService->assignDeliveryman(
            order: $order,
            deliverymanId: $deliverymanId !== null ? (int) $deliverymanId : null,
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} uchun yetkazib beruvchi yangilandi");
    }

    public function selfAssign(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('selfAssign', $order);

        $this->orderService->selfAssignDeliveryman($order, $request->user());

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} sizga biriktirildi");
    }

    public function releaseSelf(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('releaseSelf', $order);

        $this->orderService->releaseSelfFromOrder($order, $request->user());

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} dan voz kechdingiz");
    }

    public function deliver(DeliverOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('deliver', $order);

        $this->orderService->deliver(
            order: $order,
            items: $request->validated('items'),
            paidAmount: (int) $request->validated('paid_amount'),
            discount: (int) ($request->validated('discount') ?? 0),
            paidCard: (int) ($request->validated('paid_card') ?? 0),
            cardholderName: $request->validated('cardholder_name'),
            by: $request->user(),
        );

        Inertia::clearHistory();

        return back()->with('status', "Buyurtma #{$order->displayNumber()} yetkazildi");
    }

    public function invoice(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load('shop', 'items.productType', 'dealer', 'deliveryman');

        return Inertia::render('Dealer/Orders/Invoice', [
            'order' => OrderResource::make($order),
        ]);
    }

    public function editForm(Order $order): Response
    {
        $this->authorize('edit', $order);

        $dealerId = (int) $order->dealer_id;

        $order->load([
            'shop',
            'items.product',
            'items.productType',
        ]);

        $products = Product::query()
            ->forDealer($dealerId)
            ->active()
            ->with(['images', 'types' => fn ($q) => $q->where('is_active', true), 'types.images'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Dealer/Orders/Edit', [
            'order' => OrderResource::make($order),
            'products' => ProductResource::collection($products),
        ]);
    }

    public function edit(EditOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('edit', $order);

        $this->orderService->edit(
            order: $order,
            items: $request->validated('items'),
            paidAmount: (int) $request->validated('paid_amount'),
            discount: (int) ($request->validated('discount') ?? 0),
            paidCard: (int) ($request->validated('paid_card') ?? 0),
            cardholderName: $request->validated('cardholder_name'),
            by: $request->user(),
        );

        return redirect()
            ->route('dealer.orders.show', $order)
            ->with('status', "Buyurtma #{$order->displayNumber()} tahrirlandi");
    }
}
