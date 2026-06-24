<?php

declare(strict_types=1);

namespace Tests\Feature\MiniApp;

use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\ShopFavorite;
use App\Models\ShopMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OutOfStockVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_stock_products_visible_when_setting_enabled(): void
    {
        $dealer = Dealer::factory()->create(['show_out_of_stock' => true]);
        Product::factory()->for($dealer)->create(['name' => 'InStock', 'stock' => 5, 'has_types' => false]);
        Product::factory()->for($dealer)->create(['name' => 'Empty', 'stock' => 0, 'has_types' => false]);
        Product::factory()->for($dealer)->create(['name' => 'Negative', 'stock' => -2, 'has_types' => false]);

        $response = $this->getJson("/api/miniapp/{$dealer->id}/products?dev_telegram_id=1");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('InStock', $names);
        $this->assertContains('Empty', $names);
        $this->assertContains('Negative', $names);
    }

    public function test_out_of_stock_products_hidden_when_setting_disabled(): void
    {
        $dealer = Dealer::factory()->create(['show_out_of_stock' => false]);
        $inStock = Product::factory()->for($dealer)->create(['name' => 'InStock', 'stock' => 5, 'has_types' => false]);
        $empty = Product::factory()->for($dealer)->create(['name' => 'Empty', 'stock' => 0, 'has_types' => false]);
        $negative = Product::factory()->for($dealer)->create(['name' => 'Negative', 'stock' => -2, 'has_types' => false]);

        $response = $this->getJson("/api/miniapp/{$dealer->id}/products?dev_telegram_id=1");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('InStock', $names);
        $this->assertNotContains('Empty', $names);
        $this->assertNotContains('Negative', $names);

        $this->getJson("/api/miniapp/{$dealer->id}/products/{$inStock->id}?dev_telegram_id=1")->assertOk();
        $this->getJson("/api/miniapp/{$dealer->id}/products/{$empty->id}?dev_telegram_id=1")->assertNotFound();
        $this->getJson("/api/miniapp/{$dealer->id}/products/{$negative->id}?dev_telegram_id=1")->assertNotFound();
    }

    public function test_favorites_endpoint_hides_out_of_stock_when_setting_disabled(): void
    {
        $dealer = Dealer::factory()->create(['show_out_of_stock' => false]);
        $shop = Shop::factory()->for($dealer)->create();
        $member = ShopMember::factory()->for($shop)->create(['telegram_id' => 555]);

        $inStock = Product::factory()->for($dealer)->create(['name' => 'FavIn', 'stock' => 3, 'has_types' => false]);
        $empty = Product::factory()->for($dealer)->create(['name' => 'FavEmpty', 'stock' => 0, 'has_types' => false]);

        ShopFavorite::factory()->for($shop)->for($inStock)->create();
        ShopFavorite::factory()->for($shop)->for($empty)->create();

        $response = $this->getJson("/api/miniapp/{$dealer->id}/favorites?dev_telegram_id={$member->telegram_id}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('FavIn', $names);
        $this->assertNotContains('FavEmpty', $names);
    }

    public function test_typed_product_shown_if_any_active_type_in_stock(): void
    {
        $dealer = Dealer::factory()->create(['show_out_of_stock' => false]);
        $product = Product::factory()->for($dealer)->create(['name' => 'Mixed', 'stock' => 0, 'has_types' => true]);
        ProductType::factory()->for($product)->create(['stock' => 0, 'is_active' => true]);
        ProductType::factory()->for($product)->create(['stock' => 10, 'is_active' => true]);

        $allEmpty = Product::factory()->for($dealer)->create(['name' => 'AllEmpty', 'stock' => 0, 'has_types' => true]);
        ProductType::factory()->for($allEmpty)->create(['stock' => 0, 'is_active' => true]);
        ProductType::factory()->for($allEmpty)->create(['stock' => 0, 'is_active' => true]);

        $inactiveStock = Product::factory()->for($dealer)->create(['name' => 'InactiveStock', 'stock' => 0, 'has_types' => true]);
        ProductType::factory()->for($inactiveStock)->create(['stock' => 10, 'is_active' => false]);

        $response = $this->getJson("/api/miniapp/{$dealer->id}/products?dev_telegram_id=1");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Mixed', $names);
        $this->assertNotContains('AllEmpty', $names);
        $this->assertNotContains('InactiveStock', $names);
    }
}
