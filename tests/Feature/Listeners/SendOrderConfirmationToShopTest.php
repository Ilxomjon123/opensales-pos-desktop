<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Events\OrderCreated;
use App\Listeners\SendOrderConfirmationToShop;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

final class SendOrderConfirmationToShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_message_sent_when_order_creator_is_shop_member(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => -100_000]);
        $member = ShopMember::factory()->for($shop)->create([
            'telegram_id' => 1_360_121_104,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'member_id' => $member->id,
            'status' => OrderStatus::PENDING,
            'total' => 50_000,
        ]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->once()->andReturnTrue();

        $this->bindBotFactory($bot);

        app(SendOrderConfirmationToShop::class)->handle(new OrderCreated($order));
    }

    public function test_each_active_member_receives_one_message(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $creator = ShopMember::factory()->for($shop)->create([
            'telegram_id' => 100,
            'is_active' => true,
        ]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 200,
            'is_active' => true,
        ]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 300,
            'is_active' => false,
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'member_id' => $creator->id,
            'status' => OrderStatus::PENDING,
            'total' => 50_000,
        ]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->twice()->andReturnTrue();

        $this->bindBotFactory($bot);

        app(SendOrderConfirmationToShop::class)->handle(new OrderCreated($order));
    }

    public function test_jami_uses_live_items_sum_not_stale_total_snapshot(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 1_360_121_104,
            'is_active' => true,
        ]);

        // total ustuni eskirib qolgan: 612_000, item subtotal yig'indisi 570_000
        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 612_000,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Hordiq',
            'price' => 5_000,
            'pack_price' => null,
            'qty' => 6,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Mentos',
            'price' => 4_500,
            'pack_price' => 108_000,
            'qty' => 96,
            'unit' => ProductUnit::DONA,
            'pack_size' => 24,
            'pack_qty' => 4,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Styx',
            'price' => 1_500,
            'pack_price' => null,
            'qty' => 72,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ]);

        $captured = '';
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (string $text = '', ...$rest) use (&$captured) {
                $captured = $text;

                return null;
            });

        $this->bindBotFactory($bot);

        app(SendOrderConfirmationToShop::class)->handle(new OrderCreated($order));

        $this->assertStringContainsString('Jami: 570,000 so\'m', $captured);
        $this->assertStringNotContainsString('612,000', $captured);
    }

    public function test_message_is_localized_to_member_locale(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => -50_000]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 555,
            'is_active' => true,
            'locale' => 'ru',
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 50_000,
        ]);

        $captured = '';
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (string $text = '', ...$rest) use (&$captured) {
                $captured = $text;

                return null;
            });

        $this->bindBotFactory($bot);

        app(SendOrderConfirmationToShop::class)->handle(new OrderCreated($order));

        $this->assertStringContainsString('Заказ #', $captured);
        $this->assertStringContainsString('Итого:', $captured);
        $this->assertStringNotContainsString('Jami:', $captured);
    }

    private function bindBotFactory(Nutgram $bot): void
    {
        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot)
            {
                // Konstruktorni override qilamiz — Application kerak emas (test uchun)
            }

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });
    }
}
