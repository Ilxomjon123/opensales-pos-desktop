<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\ProductPriceChanged;
use App\Jobs\SendProductNotificationJob;
use App\Listeners\NotifyPriceChange;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class NotifyPriceChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_to_each_active_member_deduped(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_price_change' => true]);
        $product = Product::factory()->for($dealer)->create(['price' => 12_000]);

        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shopA)->create(['telegram_id' => 100, 'is_active' => true]);
        ShopMember::factory()->for($shopB)->create(['telegram_id' => 200, 'is_active' => true]);
        ShopMember::factory()->for($shopA)->create(['telegram_id' => 300, 'is_active' => false]);

        app(NotifyPriceChange::class)->handle(
            new ProductPriceChanged($product, 10_000, null, 12_000, null),
        );

        Bus::assertDispatchedTimes(SendProductNotificationJob::class, 2);
        Bus::assertDispatched(SendProductNotificationJob::class, fn (SendProductNotificationJob $job): bool => $job->chatId === 100);
        Bus::assertDispatched(SendProductNotificationJob::class, fn (SendProductNotificationJob $job): bool => $job->chatId === 200);
    }

    public function test_does_not_dispatch_when_flag_disabled(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_price_change' => false]);
        $product = Product::factory()->for($dealer)->create(['price' => 12_000]);
        $shop = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shop)->create(['telegram_id' => 100, 'is_active' => true]);

        app(NotifyPriceChange::class)->handle(
            new ProductPriceChanged($product, 10_000, null, 12_000, null),
        );

        Bus::assertNotDispatched(SendProductNotificationJob::class);
    }

    public function test_job_text_includes_old_and_new_price(): void
    {
        Bus::fake();

        $dealer = Dealer::factory()->create(['notify_on_price_change' => true]);
        $product = Product::factory()->for($dealer)->create(['price' => 12_000, 'name' => 'Coca-Cola']);
        $shop = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->for($shop)->create(['telegram_id' => 100, 'is_active' => true]);

        app(NotifyPriceChange::class)->handle(
            new ProductPriceChanged($product, 10_000, null, 12_000, null),
        );

        Bus::assertDispatched(SendProductNotificationJob::class, function (SendProductNotificationJob $job): bool {
            return str_contains($job->text, 'Coca-Cola')
                && str_contains($job->text, '<s>10,000</s>')
                && str_contains($job->text, '<b>12,000');
        });
    }
}
