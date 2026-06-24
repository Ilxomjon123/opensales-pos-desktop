<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\ProductVisibility;
use App\Models\Dealer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_visibility_is_bot_only(): void
    {
        $product = Product::factory()->for(Dealer::factory())->create();

        $this->assertSame(ProductVisibility::BOT_ONLY, $product->visibility);
    }

    public function test_visible_in_bot_scope_includes_bot_only_and_both(): void
    {
        $dealer = Dealer::factory()->create();
        $botOnly = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::BOT_ONLY]);
        $marketOnly = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::MARKETPLACE_ONLY]);
        $both = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::BOTH]);

        $ids = Product::query()->visibleInBot()->pluck('id');

        $this->assertTrue($ids->contains($botOnly->id));
        $this->assertTrue($ids->contains($both->id));
        $this->assertFalse($ids->contains($marketOnly->id));
    }

    public function test_visible_in_marketplace_scope_includes_marketplace_only_and_both(): void
    {
        $dealer = Dealer::factory()->create();
        $botOnly = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::BOT_ONLY]);
        $marketOnly = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::MARKETPLACE_ONLY]);
        $both = Product::factory()->for($dealer)->create(['visibility' => ProductVisibility::BOTH]);

        $ids = Product::query()->visibleInMarketplace()->pluck('id');

        $this->assertTrue($ids->contains($marketOnly->id));
        $this->assertTrue($ids->contains($both->id));
        $this->assertFalse($ids->contains($botOnly->id));
    }
}
