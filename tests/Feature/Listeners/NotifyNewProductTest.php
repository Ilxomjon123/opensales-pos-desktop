<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\ProductCreated;
use App\Jobs\SendProductNotificationJob;
use App\Listeners\NotifyNewProduct;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class NotifyNewProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_to_each_active_member_deduped(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_new_product' => true]);
        $product = Product::factory()->for($dealer)->create(['is_active' => true]);

        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shopA)->create(['telegram_id' => 100, 'is_active' => true]);
        ShopMember::factory()->for($shopB)->create(['telegram_id' => 200, 'is_active' => true]);
        ShopMember::factory()->for($shopA)->create(['telegram_id' => 300, 'is_active' => false]);

        app(NotifyNewProduct::class)->handle(new ProductCreated($product));

        Bus::assertDispatchedTimes(SendProductNotificationJob::class, 2);
    }

    public function test_does_not_dispatch_when_flag_disabled(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_new_product' => false]);
        $product = Product::factory()->for($dealer)->create(['is_active' => true]);
        $shop = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shop)->create(['telegram_id' => 100, 'is_active' => true]);

        app(NotifyNewProduct::class)->handle(new ProductCreated($product));

        Bus::assertNotDispatched(SendProductNotificationJob::class);
    }

    public function test_does_not_dispatch_when_product_inactive(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_new_product' => true]);
        $product = Product::factory()->for($dealer)->create(['is_active' => false]);
        $shop = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shop)->create(['telegram_id' => 100, 'is_active' => true]);

        app(NotifyNewProduct::class)->handle(new ProductCreated($product));

        Bus::assertNotDispatched(SendProductNotificationJob::class);
    }
}
