<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\ProductVisibility;
use App\Exceptions\Domain\BelowMinOrderAmountException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\User;
use App\Services\MarketplaceFinanceService;
use App\Services\MarketplaceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MarketplaceOrderService
    {
        return app(MarketplaceOrderService::class);
    }

    public function test_full_flow_place_accept_receive(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        $buyerUser = User::factory()->create(['dealer_id' => $buyer->id]);

        $product = Product::factory()->for($seller)->create([
            'price' => 10_000,
            'stock' => 100,
            'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);

        // 1) Joylashtirildi
        $order = $this->service()->placeOrder($buyer, [
            ['product_id' => $product->id, 'qty' => 10],
        ], note: 'test');

        $this->assertSame(OrderChannel::MARKETPLACE, $order->channel);
        $this->assertSame($seller->id, $order->dealer_id);
        $this->assertSame($buyer->id, $order->buyer_dealer_id);
        $this->assertNull($order->shop_id);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(100_000, (int) $order->total);

        // 2) Sotuvchi qabul qiladi — stok kamayadi
        $this->service()->accept($order);
        $this->assertSame(90.0, (float) $product->fresh()->stock);
        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);

        // 3) Yo'lda → yetkazildi
        $this->service()->ship($order->fresh());
        $this->service()->markDelivered($order->fresh());

        // 4) Xaridor qabul qiladi — omboriga kirim + qarz
        $this->service()->confirmReceived($order->fresh(), $buyerUser);

        $this->assertSame(OrderStatus::RECEIVED, $order->fresh()->status);

        // Xaridor katalogida mahsulot yaratildi, stok kirim qilindi
        $buyerProduct = Product::query()->forDealer($buyer->id)
            ->whereRaw('LOWER(name) = LOWER(?)', [$product->name])
            ->first();
        $this->assertNotNull($buyerProduct);
        $this->assertSame(10.0, (float) $buyerProduct->stock);

        // Dillerlararo qarz: xaridor sotuvchiga 100k qarzdor
        $finance = app(MarketplaceFinanceService::class);
        $this->assertSame(100_000, $finance->balanceBetween($seller->id, $buyer->id));
        $this->assertSame(-100_000, $finance->balanceBetween($buyer->id, $seller->id));
    }

    public function test_order_below_seller_marketplace_minimum_is_rejected(): void
    {
        $seller = Dealer::factory()->create([
            'sells_on_marketplace' => true,
            'marketplace_min_order_amount' => 100_000,
        ]);
        $buyer = Dealer::factory()->create();
        $product = Product::factory()->for($seller)->create([
            'price' => 10_000, 'stock' => 50, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);

        $this->expectException(BelowMinOrderAmountException::class);

        // 5 × 10k = 50k < 100k minimum
        $this->service()->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 5]]);
    }

    public function test_order_meeting_seller_marketplace_minimum_is_allowed(): void
    {
        $seller = Dealer::factory()->create([
            'sells_on_marketplace' => true,
            'marketplace_min_order_amount' => 100_000,
        ]);
        $buyer = Dealer::factory()->create();
        $product = Product::factory()->for($seller)->create([
            'price' => 10_000, 'stock' => 50, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);

        $order = $this->service()->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 10]]);

        $this->assertSame(100_000, (int) $order->total);
    }

    public function test_cancel_after_accept_restores_seller_stock(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        $product = Product::factory()->for($seller)->create([
            'price' => 5_000, 'stock' => 50, 'visibility' => ProductVisibility::BOTH,
        ]);

        $order = $this->service()->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 20]]);
        $this->service()->accept($order);
        $this->assertSame(30.0, (float) $product->fresh()->stock);

        $this->service()->cancel($order->fresh(), reason: 'передумал');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(50.0, (float) $product->fresh()->stock);
    }

    public function test_cannot_buy_non_marketplace_product(): void
    {
        $seller = Dealer::factory()->create();
        $buyer = Dealer::factory()->create();
        $product = Product::factory()->for($seller)->create([
            'visibility' => ProductVisibility::BOT_ONLY, 'stock' => 10,
        ]);

        $this->expectException(ProductUnavailableException::class);

        $this->service()->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);
    }

    public function test_cannot_buy_own_product(): void
    {
        $dealer = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $product = Product::factory()->for($dealer)->create([
            'visibility' => ProductVisibility::MARKETPLACE_ONLY, 'stock' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->placeOrder($dealer, [['product_id' => $product->id, 'qty' => 1]]);
    }
}
