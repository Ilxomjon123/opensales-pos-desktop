<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sotuvchi tomoni — distribyutorga Birjadan kelgan sotuvlar.
 */
final class MarketplaceSaleController extends Controller
{
    public function __construct(private readonly MarketplaceOrderService $orders) {}

    public function accept(Request $request, Order $order): RedirectResponse
    {
        $this->assertSeller($request, $order);
        $this->orders->accept($order);

        return back()->with('status', 'Buyurtma qabul qilindi');
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        $this->assertSeller($request, $order);
        $this->orders->ship($order);

        return back()->with('status', 'Yo\'lga chiqarildi');
    }

    public function deliver(Request $request, Order $order): RedirectResponse
    {
        $this->assertSeller($request, $order);
        $this->orders->markDelivered($order);

        return back()->with('status', 'Yetkazildi deb belgilandi');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->assertSeller($request, $order);
        $this->orders->cancel($order, $request->user(), $request->string('reason')->toString() ?: null);

        return back()->with('status', 'Buyurtma bekor qilindi');
    }

    private function assertSeller(Request $request, Order $order): void
    {
        abort_unless(
            $order->channel === OrderChannel::MARKETPLACE
                && (int) $order->dealer_id === (int) $request->user()->dealer_id,
            403,
        );
    }
}
