<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopFavorite;
use App\Services\FavoriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FavoriteServiceTest extends TestCase
{
    use RefreshDatabase;

    private FavoriteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FavoriteService::class);
    }

    public function test_add_creates_favorite_and_invalidates_cache(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create();

        $this->assertFalse($this->service->isFavorite($shop->id, $product->id));

        $this->service->add($shop, $product);

        $this->assertTrue($this->service->isFavorite($shop->id, $product->id));
        $this->assertDatabaseHas('shop_favorites', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_add_is_idempotent(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create();

        $this->service->add($shop, $product);
        $this->service->add($shop, $product);

        $this->assertSame(1, ShopFavorite::query()->count());
    }

    public function test_add_rejects_product_from_different_dealer(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealerA)->create();
        $product = Product::factory()->for($dealerB)->create();

        $this->service->add($shop, $product);

        $this->assertSame(0, ShopFavorite::query()->count());
    }

    public function test_toggle_adds_then_removes(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create();

        $this->assertTrue($this->service->toggle($shop, $product));
        $this->assertTrue($this->service->isFavorite($shop->id, $product->id));

        $this->assertFalse($this->service->toggle($shop, $product));
        $this->assertFalse($this->service->isFavorite($shop->id, $product->id));
    }

    public function test_remove_clears_cache(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create();

        $this->service->add($shop, $product);
        $this->assertNotEmpty($this->service->productIds($shop->id));

        $this->service->remove($shop, $product->id);
        $this->assertSame([], $this->service->productIds($shop->id));
    }

    public function test_product_ids_returns_only_own_shop(): void
    {
        $dealer = Dealer::factory()->create();
        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create();

        $this->service->add($shopA, $product);

        $this->assertSame([$product->id], $this->service->productIds($shopA->id));
        $this->assertSame([], $this->service->productIds($shopB->id));
    }
}
