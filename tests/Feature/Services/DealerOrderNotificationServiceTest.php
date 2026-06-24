<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\DealerOrderNotificationService;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use Tests\TestCase;

final class DealerOrderNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_call_sends_message_and_saves_message_id(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);
        $captured = '';

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (string $text = '', ...$rest) use (&$captured) {
                $captured = $text;

                return $this->fakeMessage(9999);
            });
        $bot->shouldNotReceive('editMessageText');

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertSame(9999, (int) $order->fresh()->dealer_notification_message_id);
        $this->assertStringContainsString('Buyurtma #'.$order->number, $captured);
        $this->assertStringContainsString('Kutilmoqda', $captured);
    }

    public function test_subsequent_call_edits_existing_message(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING);
        $order->update(['dealer_notification_message_id' => 12345]);

        $captured = '';
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('editMessageText')
            ->once()
            ->andReturnUsing(function (string $text = '', ...$rest) use (&$captured) {
                $captured = $text;

                return true;
            });
        $bot->shouldNotReceive('sendMessage');

        $this->serviceWith($bot)->sendOrUpdate($order->fresh());

        $this->assertStringContainsString('Yetkazilmoqda', $captured);
    }

    public function test_transient_edit_failure_rethrows_and_does_not_send_new_message(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING);
        $order->update(['dealer_notification_message_id' => 12345]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('editMessageText')
            ->once()
            ->andThrow(new \RuntimeException('cURL error 28: Connection timed out after 30001 milliseconds'));
        $bot->shouldNotReceive('sendMessage');

        $this->expectException(\RuntimeException::class);

        try {
            $this->serviceWith($bot)->sendOrUpdate($order->fresh());
        } finally {
            // message_id o'zgarmasligi kerak — keyingi retry o'sha xabarni edit qiladi.
            $this->assertSame(12345, (int) $order->fresh()->dealer_notification_message_id);
        }
    }

    public function test_permanent_edit_failure_sends_new_message(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING);
        $order->update(['dealer_notification_message_id' => 12345]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('editMessageText')
            ->once()
            ->andThrow(new \RuntimeException('Bad Request: message to edit not found'));
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturn($this->fakeMessage(55555));

        $this->serviceWith($bot)->sendOrUpdate($order->fresh());

        $this->assertSame(55555, (int) $order->fresh()->dealer_notification_message_id);
    }

    public function test_no_send_when_dealer_has_no_telegram_chat(): void
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => null]);
        $shop = Shop::factory()->for($dealer)->create();

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 0,
        ]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldNotReceive('sendMessage');
        $bot->shouldNotReceive('editMessageText');

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertNull($order->fresh()->dealer_notification_message_id);
    }

    public function test_message_includes_assigned_deliveryman_name(): void
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create();
        $deliveryman = User::factory()->create([
            'dealer_id' => $dealer->id,
            'role' => UserRole::DELIVERYMAN,
            'name' => 'Akmal',
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::ASSEMBLING,
            'total' => 0,
            'deliveryman_id' => $deliveryman->id,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Test',
            'price' => 1000,
            'qty' => 1,
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

                return $this->fakeMessage(1);
            });

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertStringContainsString('Yetkazib beruvchi: Akmal', $captured);
        $this->assertStringContainsString('Tayyorlandi', $captured);
    }

    public function test_apostrophe_in_address_is_not_html_escaped(): void
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create([
            'address' => "Namangan viloyati, To'raqo'rg'on tumani, D124",
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 0,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Test',
            'price' => 1000,
            'qty' => 1,
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

                return $this->fakeMessage(1);
            });

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertStringContainsString("To'raqo'rg'on", $captured);
        $this->assertStringNotContainsString('&apos;', $captured);
        $this->assertStringNotContainsString('&#039;', $captured);
    }

    public function test_message_includes_map_button_when_shop_has_coordinates(): void
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create([
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'map_provider' => 'yandex',
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 0,
        ]);

        $keyboard = null;
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (...$args) use (&$keyboard) {
                $keyboard = $this->extractKeyboard($args);

                return $this->fakeMessage(1);
            });

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertNotNull($keyboard);
        $url = $keyboard->inline_keyboard[0][0]->url;
        $this->assertSame('https://yandex.uz/maps/?pt=69.240562,41.311081&z=16&l=map', $url);
    }

    public function test_message_has_no_map_button_when_shop_lacks_coordinates(): void
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 0,
        ]);

        $keyboard = 'unset';
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (...$args) use (&$keyboard) {
                $keyboard = $this->extractKeyboard($args);

                return $this->fakeMessage(1);
            });

        $this->serviceWith($bot)->sendOrUpdate($order);

        $this->assertNull($keyboard);
    }

    private function extractKeyboard(array $args): ?InlineKeyboardMarkup
    {
        foreach ($args as $arg) {
            if ($arg instanceof InlineKeyboardMarkup) {
                return $arg;
            }
        }

        return null;
    }

    private function makeOrder(OrderStatus $status): Order
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create();

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => $status,
            'total' => 0,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Test',
            'price' => 1000,
            'qty' => 1,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ]);

        return $order;
    }

    private function fakeMessage(int $id): Message
    {
        $message = Mockery::mock(Message::class);
        $message->message_id = $id;

        return $message;
    }

    private function serviceWith(Nutgram $bot): DealerOrderNotificationService
    {
        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot) {}

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });

        return app(DealerOrderNotificationService::class);
    }
}
