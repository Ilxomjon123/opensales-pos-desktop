<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreMarketplaceOrderRequest;
use App\Http\Resources\Marketplace\MarketplaceOrderResource;
use App\Models\Dealer;
use App\Models\Order;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Xaridor tomoni — diller Birjadan qilgan xaridlari.
 */
final class MarketplaceOrderController extends Controller
{
    public function __construct(private readonly MarketplaceOrderService $orders) {}

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $orders = Order::query()
            ->marketplace()
            ->forBuyerDealer($dealerId)
            ->with(['dealer', 'items'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Dealer/Marketplace/Orders', [
            'orders' => MarketplaceOrderResource::collection($orders),
            'filters' => $request->only(['status']),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(StoreMarketplaceOrderRequest $request): RedirectResponse
    {
        $buyer = Dealer::query()->findOrFail($request->user()->dealer_id);

        $this->orders->placeOrder(
            buyer: $buyer,
            items: $request->validated('items'),
            note: $request->validated('note'),
        );

        return redirect()
            ->route('dealer.marketplace.orders.index')
            ->with('status', 'Buyurtma Birjaga yuborildi');
    }

    public function show(Request $request, Order $order): Response
    {
        $this->assertBuyer($request, $order);

        $order->load(['dealer', 'items']);

        return Inertia::render('Dealer/Marketplace/OrderShow', [
            'order' => MarketplaceOrderResource::make($order),
        ]);
    }

    public function receive(Request $request, Order $order): RedirectResponse
    {
        $this->assertBuyer($request, $order);

        $this->orders->confirmReceived($order, $request->user());

        return back()->with('status', 'Tovar qabul qilindi, omborga kirim qilindi');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->assertBuyer($request, $order);

        $this->orders->cancel($order, $request->user(), $request->string('reason')->toString() ?: null);

        return back()->with('status', 'Buyurtma bekor qilindi');
    }

    private function assertBuyer(Request $request, Order $order): void
    {
        abort_unless(
            $order->channel === OrderChannel::MARKETPLACE
                && (int) $order->buyer_dealer_id === (int) $request->user()->dealer_id,
            403,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (OrderStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            OrderStatus::cases(),
        );
    }
}
