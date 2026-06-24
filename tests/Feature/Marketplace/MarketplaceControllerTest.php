<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Enums\ProductVisibility;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\MarketplaceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dealerUser(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);
    }

    public function test_buyer_places_order_then_seller_and_buyer_drive_lifecycle(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        $sellerUser = $this->dealerUser($seller);
        $buyerUser = $this->dealerUser($buyer);

        $product = Product::factory()->for($seller)->create([
            'price' => 8_000, 'stock' => 40, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);

        // Buyer joylashtiradi
        $this->actingAs($buyerUser)
            ->post(route('dealer.marketplace.orders.store'), [
                'items' => [['product_id' => $product->id, 'qty' => 5]],
            ])
            ->assertRedirect(route('dealer.marketplace.orders.index'));

        $order = Order::query()->marketplace()->firstOrFail();
        $this->assertSame($buyer->id, $order->buyer_dealer_id);
        $this->assertSame($seller->id, $order->dealer_id);
        $this->assertSame(40_000, (int) $order->total);

        // Seller qabul qiladi
        $this->actingAs($sellerUser)
            ->post(route('dealer.marketplace.sales.accept', $order))
            ->assertRedirect();
        $this->assertSame(35.0, (float) $product->fresh()->stock);
        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);

        // ship → deliver
        $this->actingAs($sellerUser)->post(route('dealer.marketplace.sales.ship', $order));
        $this->actingAs($sellerUser)->post(route('dealer.marketplace.sales.deliver', $order));

        // Buyer qabul qiladi
        $this->actingAs($buyerUser)
            ->post(route('dealer.marketplace.orders.receive', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::RECEIVED, $order->fresh()->status);
        $this->assertDatabaseHas('marketplace_balances', [
            'dealer_id' => $seller->id, 'partner_dealer_id' => $buyer->id, 'balance' => 40_000,
        ]);
    }

    public function test_buyer_cannot_act_on_others_order(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        $stranger = $this->dealerUser(Dealer::factory()->create());

        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::BOTH,
        ]);
        $order = app(MarketplaceOrderService::class)
            ->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        $this->actingAs($stranger)
            ->post(route('dealer.marketplace.orders.receive', $order))
            ->assertForbidden();
    }

    public function test_dealer_views_and_updates_marketplace_settings(): void
    {
        $dealer = Dealer::factory()->create(['marketplace_min_order_amount' => 0]);
        $user = $this->dealerUser($dealer);

        $this->actingAs($user)
            ->get(route('dealer.marketplace.settings.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Marketplace/Settings')
                ->where('dealer.data.marketplace_min_order_amount', 0));

        $this->actingAs($user)
            ->put(route('dealer.marketplace.settings.update'), [
                'marketplace_min_order_amount' => 150_000,
            ])
            ->assertRedirect();

        $this->assertSame(150_000, (int) $dealer->fresh()->marketplace_min_order_amount);
    }

    public function test_browse_excludes_own_and_bot_only_products(): void
    {
        $me = Dealer::factory()->create();
        $meUser = $this->dealerUser($me);
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);

        Product::factory()->for($me)->create(['visibility' => ProductVisibility::MARKETPLACE_ONLY, 'stock' => 5]);
        Product::factory()->for($seller)->create(['visibility' => ProductVisibility::BOT_ONLY, 'stock' => 5]);
        $visible = Product::factory()->for($seller)->create(['visibility' => ProductVisibility::BOTH, 'stock' => 5]);

        $this->actingAs($meUser)
            ->get(route('dealer.marketplace.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Marketplace/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $visible->id));
    }
}
