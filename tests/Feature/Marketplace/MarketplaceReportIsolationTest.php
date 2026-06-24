<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\OrderChannel;
use App\Enums\ProductVisibility;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\MarketplaceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceReportIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_orders_excluded_from_dealer_bot_order_list(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();

        // Bot (shop) buyurtma — sotuvchining o'z shop savdosi
        $botOrder = Order::factory()->create(['dealer_id' => $seller->id]);

        // Marketplace sotuv — sotuvchi dealer_id bilan, lekin channel=marketplace
        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);
        $mpOrder = app(MarketplaceOrderService::class)
            ->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        $ids = app(OrderRepository::class)->paginateForDealer($seller->id)->pluck('id');

        $this->assertTrue($ids->contains($botOrder->id), 'Bot buyurtma ko\'rinishi kerak');
        $this->assertFalse($ids->contains($mpOrder->id), 'Marketplace buyurtma bot ro\'yxatida ko\'rinmasligi kerak');
    }

    public function test_manual_shop_orders_included_marketplace_excluded(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();

        // Qo'lda yaratilgan shop buyurtma — shop savdosi, hisobotda bo'lishi kerak
        $manual = Order::factory()->create([
            'dealer_id' => $seller->id,
            'channel' => OrderChannel::MANUAL,
        ]);

        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::BOTH,
        ]);
        $mp = app(MarketplaceOrderService::class)->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        $ids = app(OrderRepository::class)->paginateForDealer($seller->id)->pluck('id');

        $this->assertTrue($ids->contains($manual->id), 'Qo\'lda buyurtma shop ro\'yxatida bo\'lishi kerak');
        $this->assertFalse($ids->contains($mp->id), 'Marketplace shop ro\'yxatida bo\'lmasligi kerak');
    }

    public function test_marketplace_order_opens_on_shared_orders_page(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        $sellerUser = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $seller->id]);

        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);
        $mp = app(MarketplaceOrderService::class)->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        // Birja sotuvi alohida sahifasiz — Buyurtmalar sahifasida ochiladi.
        $this->actingAs($sellerUser)
            ->get("/dealer/orders/{$mp->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dealer/Orders/Show'));
    }

    public function test_marketplace_order_shown_in_shop_orders_index_with_buyer_as_customer(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create(['name' => 'Kichik Diller']);
        $sellerUser = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $seller->id]);

        Order::factory()->create(['dealer_id' => $seller->id, 'channel' => OrderChannel::MANUAL]);
        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::MARKETPLACE_ONLY,
        ]);
        $mp = app(MarketplaceOrderService::class)->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        // Unified ro'yxat: manual + marketplace ikkalasi ko'rinadi, xaridor "Mijoz" sifatida.
        $this->actingAs($sellerUser)
            ->get('/dealer/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Orders/Index')
                ->has('orders.data', 2)
                ->where('orders.data', fn ($rows) => collect($rows)->contains(
                    fn ($r) => $r['id'] === $mp->id && $r['customer_name'] === 'Kichik Diller'
                )));
    }

    public function test_bot_and_marketplace_scopes_partition_orders(): void
    {
        $seller = Dealer::factory()->create(['sells_on_marketplace' => true]);
        $buyer = Dealer::factory()->create();
        Order::factory()->create(['dealer_id' => $seller->id]);
        $product = Product::factory()->for($seller)->create([
            'price' => 1000, 'stock' => 10, 'visibility' => ProductVisibility::BOTH,
        ]);
        app(MarketplaceOrderService::class)->placeOrder($buyer, [['product_id' => $product->id, 'qty' => 1]]);

        $this->assertSame(1, Order::query()->forDealer($seller->id)->bot()->count());
        $this->assertSame(1, Order::query()->forDealer($seller->id)->marketplace()->count());
    }
}
