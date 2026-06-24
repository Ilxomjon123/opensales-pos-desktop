<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TG = 1001;

    private const SHOP = 1;

    private CartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CartService::class);
    }

    public function test_add_and_get(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100, 'price' => 5_000]);

        $cart = $this->service->addItem(self::TG, self::SHOP, $product, 3);

        $this->assertCount(1, $cart);
        $this->assertSame(15_000, $cart->total());

        $fromCache = $this->service->get(self::TG, self::SHOP);
        $this->assertSame(15_000, $fromCache->total());
    }

    public function test_add_same_product_merges_in_cache(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100, 'price' => 1_000]);

        $this->service->addItem(self::TG, self::SHOP, $product, 2);
        $cart = $this->service->addItem(self::TG, self::SHOP, $product, 3);

        $this->assertCount(1, $cart);
        $this->assertSame(5, (int) $cart->get($product->id)?->qty);
    }

    public function test_set_item_qty_replaces_quantity(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100, 'price' => 2_000]);

        $this->service->addItem(self::TG, self::SHOP, $product, 3);
        $cart = $this->service->setItemQty(self::TG, self::SHOP, $product, 7);

        $this->assertSame(7, (int) $cart->get($product->id)?->qty);
        $this->assertSame(14_000, $cart->total());
    }

    public function test_set_item_pack_qty_updates_both_pack_and_unit(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100, 'price' => 1_000, 'pack_size' => 6]);

        $this->service->addByPack(self::TG, self::SHOP, $product, 1);
        $cart = $this->service->setItemPackQty(self::TG, self::SHOP, $product, 3);

        $item = $cart->get($product->id);
        $this->assertSame(3, $item?->packQty);
        $this->assertSame(18, (int) $item?->qty);
    }

    public function test_set_item_qty_allows_more_than_stock(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 5]);

        $this->service->addItem(self::TG, self::SHOP, $product, 2);
        $cart = $this->service->setItemQty(self::TG, self::SHOP, $product, 9);

        $this->assertSame(9, (int) $cart->get($product->id)?->qty);
    }

    public function test_set_item_qty_noop_when_item_missing(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100]);

        $cart = $this->service->setItemQty(self::TG, self::SHOP, $product, 4);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_remove_item(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100]);

        $this->service->addItem(self::TG, self::SHOP, $product, 1);
        $cart = $this->service->removeItem(self::TG, self::SHOP, $product->id);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_clear(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100]);

        $this->service->addItem(self::TG, self::SHOP, $product, 2);
        $this->service->clear(self::TG, self::SHOP);

        $this->assertTrue($this->service->get(self::TG, self::SHOP)->isEmpty());
    }

    public function test_add_item_allows_qty_above_stock(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 2]);

        // Stock cheklovi yo'q — savatda istalgan miqdor bo'lishi mumkin
        $cart = $this->service->addItem(self::TG, self::SHOP, $product, 5);

        $this->assertSame(5, (int) $cart->get($product->id)?->qty);
    }

    public function test_format_summary(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['name' => 'Coca-Cola', 'stock' => 10, 'price' => 5_000]);

        $cart = $this->service->addItem(self::TG, self::SHOP, $product, 2);
        $summary = $this->service->formatSummary($cart);

        $this->assertStringContainsString('Savat', $summary);
        $this->assertStringContainsString('Coca-Cola', $summary);
    }

    public function test_format_summary_empty(): void
    {
        $this->assertSame('Savat bo\'sh', $this->service->formatSummary($this->service->get(999, 999)));
    }

    public function test_same_user_different_shops_have_separate_carts(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 100, 'price' => 1_000]);

        $this->service->addItem(self::TG, 1, $product, 2);
        $this->service->addItem(self::TG, 2, $product, 5);

        $this->assertSame(2, (int) $this->service->get(self::TG, 1)->get($product->id)?->qty);
        $this->assertSame(5, (int) $this->service->get(self::TG, 2)->get($product->id)?->qty);
    }
}
