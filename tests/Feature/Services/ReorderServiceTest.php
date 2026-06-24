<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\ReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReorderServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TG = 9001;

    private ReorderService $service;

    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReorderService::class);
        $this->cart = app(CartService::class);
    }

    public function test_rebuilds_cart_from_previous_order(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'price' => 10_000,
            'stock' => 50,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::DELIVERED]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10_000,
            'qty' => 3,
            'unit' => 'dona',
            'pack_size' => 1,
            'pack_qty' => null,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(1, $result['added']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(30_000, $result['cart']->total());
    }

    public function test_skips_deactivated_product(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'price' => 5_000,
            'stock' => 10,
            'is_active' => false,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 5_000,
            'qty' => 2,
            'unit' => 'dona',
            'pack_size' => 1,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(0, $result['added']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('deactivated', $result['skipped'][0]['reason']);
    }

    public function test_clamps_qty_to_available_stock(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'price' => 10_000,
            'stock' => 5,
            'pack_size' => 1,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10_000,
            'qty' => 10,
            'unit' => 'dona',
            'pack_size' => 1,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(1, $result['added']);
        $this->assertSame(50_000, $result['cart']->total());
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('partial_stock', $result['skipped'][0]['reason']);
        $this->assertSame(10, (int) $result['skipped'][0]['requested']);
        $this->assertSame(5, (int) $result['skipped'][0]['added']);
    }

    public function test_skips_when_product_stock_is_zero(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'price' => 10_000,
            'stock' => 0,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10_000,
            'qty' => 3,
            'unit' => 'dona',
            'pack_size' => 1,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(0, $result['added']);
        $this->assertSame('out_of_stock', $result['skipped'][0]['reason']);
    }

    public function test_preserves_combined_pack_and_loose_qty(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'price' => 1_000,
            'stock' => 100,
            'pack_size' => 10,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1_000,
            'qty' => 23, // 2 blok × 10 + 3 loose
            'unit' => 'dona',
            'pack_size' => 10,
            'pack_qty' => 2,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(1, $result['added']);
        $this->assertSame([], $result['skipped']);

        $cartItem = $result['cart']->get($product->id);
        $this->assertNotNull($cartItem);
        $this->assertSame(23, (int) $cartItem->qty);
        $this->assertSame(2, $cartItem->packQty);
        $this->assertSame(3, (int) $cartItem->looseQty());
    }

    public function test_clamps_combined_qty_when_stock_partial(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        // Stok 12: 1 blok (10) + 2 loose sig'adi
        $product = Product::factory()->for($dealer)->create([
            'price' => 1_000,
            'stock' => 12,
            'pack_size' => 10,
        ]);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1_000,
            'qty' => 23, // 2 blok + 3 loose so'rab edi
            'unit' => 'dona',
            'pack_size' => 10,
            'pack_qty' => 2,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(1, $result['added']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('partial_stock', $result['skipped'][0]['reason']);

        $cartItem = $result['cart']->get($product->id);
        $this->assertSame(12, (int) $cartItem->qty);
        $this->assertSame(1, $cartItem->packQty);
        $this->assertSame(2, (int) $cartItem->looseQty());
    }

    public function test_replaces_existing_cart(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $oldProduct = Product::factory()->for($dealer)->create(['stock' => 50, 'price' => 1_000]);
        $newProduct = Product::factory()->for($dealer)->create(['stock' => 50, 'price' => 2_000]);

        $this->cart->addItem(self::TG, $shop->id, $oldProduct, 5);

        $order = Order::factory()->for($dealer)->for($shop)->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $newProduct->id,
            'product_name' => $newProduct->name,
            'price' => 2_000,
            'qty' => 4,
            'unit' => 'dona',
            'pack_size' => 1,
        ]);

        $result = $this->service->execute($shop, $order->refresh(), self::TG);

        $this->assertSame(1, $result['added']);

        $cart = $this->cart->get(self::TG, $shop->id);
        $this->assertFalse($cart->has($oldProduct->id));
        $this->assertTrue($cart->has($newProduct->id));
    }
}
