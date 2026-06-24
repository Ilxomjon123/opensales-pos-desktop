<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderEdited;
use App\Listeners\SendOrderEditedToShop;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

final class SendOrderEditedToShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_active_member_receives_one_message(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        ShopMember::factory()->for($shop)->create([
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
            'status' => OrderStatus::DELIVERED,
            'total' => 60_000,
            'delivered_total' => 60_000,
            'paid_amount' => 50_000,
        ]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->twice()->andReturnTrue();

        $this->bindBotFactory($bot);

        app(SendOrderEditedToShop::class)->handle(new OrderEdited($order));
    }

    public function test_no_message_when_shop_has_no_active_members(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 100,
            'is_active' => false,
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::DELIVERED,
            'total' => 60_000,
            'delivered_total' => 60_000,
            'paid_amount' => 0,
        ]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldNotReceive('sendMessage');

        $this->bindBotFactory($bot);

        app(SendOrderEditedToShop::class)->handle(new OrderEdited($order));
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
