<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusChangeToShop;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use Tests\TestCase;

final class BlockedBotFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_bot_marks_member_blocked_and_does_not_report(): void
    {
        Exceptions::fake();

        [$dealer, $shop, $member] = $this->scenario();

        $this->bindBot(function () {
            throw new TelegramException('Forbidden: bot was blocked by the user', 403);
        });

        $this->dispatch($dealer, $shop);

        $this->assertNotNull($member->fresh()->blocked_at);
        Exceptions::assertNothingReported();
    }

    public function test_unexpected_error_is_reported_and_member_not_flagged(): void
    {
        Exceptions::fake();

        [$dealer, $shop, $member] = $this->scenario();

        $this->bindBot(function () {
            throw new TelegramException('Bad Request: message text is empty', 400);
        });

        $this->dispatch($dealer, $shop);

        $this->assertNull($member->fresh()->blocked_at);
        Exceptions::assertReported(TelegramException::class);
    }

    /**
     * @return array{0: Dealer, 1: Shop, 2: ShopMember}
     */
    private function scenario(): array
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $member = ShopMember::factory()->for($shop)->create([
            'telegram_id' => 555_000_111,
            'is_active' => true,
            'blocked_at' => null,
        ]);

        return [$dealer, $shop, $member];
    }

    private function dispatch(Dealer $dealer, Shop $shop): void
    {
        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::DELIVERING,
            'total' => 100_000,
        ]);

        app(SendOrderStatusChangeToShop::class)->handle(
            new OrderStatusChanged($order, OrderStatus::ASSEMBLING, OrderStatus::DELIVERING),
        );
    }

    private function bindBot(callable $onSend): void
    {
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->andReturnUsing($onSend);

        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot) {}

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });
    }
}
