<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PromotionScope;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PromotionService::class);
    }

    public function test_no_promotion_returns_original_price(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $this->assertSame(10_000.0, $this->service->effectivePriceFor($product));
        $this->assertSame(0, $this->service->discountPercentFor($product));
    }

    public function test_all_scope_applies_to_every_product(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['price' => 10_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::ALL,
            'discount_percent' => 10,
        ]);

        $this->assertSame(9_000.0, $this->service->effectivePriceFor($product));
        $this->assertSame(10, $this->service->discountPercentFor($product));
    }

    public function test_product_scope_only_applies_to_matching_product(): void
    {
        $dealer = Dealer::factory()->create();
        $matching = Product::factory()->for($dealer)->create(['price' => 10_000]);
        $other = Product::factory()->for($dealer)->create(['price' => 10_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::PRODUCT,
            'target_id' => $matching->id,
            'discount_percent' => 20,
        ]);

        $this->assertSame(8_000.0, $this->service->effectivePriceFor($matching));
        $this->assertSame(10_000.0, $this->service->effectivePriceFor($other));
    }

    public function test_category_scope_applies_to_category_members(): void
    {
        $dealer = Dealer::factory()->create();
        $category = ProductCategory::factory()->for($dealer)->create();
        $inCategory = Product::factory()->for($dealer)->create([
            'price' => 10_000,
            'category_id' => $category->id,
        ]);
        $notInCategory = Product::factory()->for($dealer)->create(['price' => 10_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::CATEGORY,
            'target_id' => $category->id,
            'discount_percent' => 15,
        ]);

        $this->assertSame(8_500.0, $this->service->effectivePriceFor($inCategory));
        $this->assertSame(10_000.0, $this->service->effectivePriceFor($notInCategory));
    }

    public function test_highest_discount_wins_when_multiple_match(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['price' => 100_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::ALL,
            'discount_percent' => 10,
        ]);
        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::PRODUCT,
            'target_id' => $product->id,
            'discount_percent' => 25,
        ]);

        $this->service->invalidate($dealer->id);

        $this->assertSame(25, $this->service->discountPercentFor($product));
        $this->assertSame(75_000.0, $this->service->effectivePriceFor($product));
    }

    public function test_expired_promotion_is_ignored(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['price' => 10_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::ALL,
            'discount_percent' => 10,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(10_000.0, $this->service->effectivePriceFor($product));
    }

    public function test_inactive_promotion_is_ignored(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['price' => 10_000]);

        Promotion::factory()->for($dealer)->create([
            'scope' => PromotionScope::ALL,
            'discount_percent' => 10,
            'is_active' => false,
        ]);

        $this->assertSame(10_000.0, $this->service->effectivePriceFor($product));
    }
}
