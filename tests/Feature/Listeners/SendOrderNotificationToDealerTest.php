<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Events\OrderCreated;
use App\Listeners\SendOrderNotificationToDealer;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

final class SendOrderNotificationToDealerTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_line_shows_blok_count_and_pack_size_in_kg(): void
    {
        $text = $this->dispatchWithItem([
            'product_name' => 'Lochira choko',
            'price' => 23_428.571429,
            'pack_price' => 82_000,
            'qty' => 10.5,
            'unit' => ProductUnit::KG,
            'pack_size' => 3.5,
            'pack_qty' => 3,
        ]);

        $this->assertStringContainsString(
            '1. [Lochira choko]: 3 blok = 246,000 so\'m',
            $text,
        );
    }

    public function test_item_line_shows_blok_plus_loose_remainder(): void
    {
        $text = $this->dispatchWithItem([
            'product_name' => 'Sutlik sevimlik',
            'price' => 31_000,
            'pack_price' => 124_000,
            'qty' => 4.5,
            'unit' => ProductUnit::KG,
            'pack_size' => 4,
            'pack_qty' => 1,
        ]);

        $this->assertStringContainsString(
            '1. [Sutlik sevimlik]: 1 blok + 0.5 kg = 139,500 so\'m',
            $text,
        );
    }

    public function test_item_line_for_dona_blok_omits_pack_size(): void
    {
        $text = $this->dispatchWithItem([
            'product_name' => 'Зажигалка',
            'price' => 1_200,
            'pack_price' => 60_000,
            'qty' => 150,
            'unit' => ProductUnit::DONA,
            'pack_size' => 50,
            'pack_qty' => 3,
        ]);

        $this->assertStringContainsString(
            '1. [Зажигалка]: 3 blok = 180,000 so\'m',
            $text,
        );
    }

    public function test_item_line_for_dona_blok_with_loose_remainder(): void
    {
        $text = $this->dispatchWithItem([
            'product_name' => 'Hordiq',
            'price' => 5_000,
            'pack_price' => 20_000,
            'qty' => 19,
            'unit' => ProductUnit::DONA,
            'pack_size' => 4,
            'pack_qty' => 4,
        ]);

        $this->assertStringContainsString(
            '1. [Hordiq]: 4 blok + 3 dona = 95,000 so\'m',
            $text,
        );
    }

    public function test_message_includes_shop_address_when_fields_present(): void
    {
        $text = $this->dispatchWithShopOverrides([
            'region' => 'Toshkent viloyati',
            'district' => 'Chilonzor',
            'address' => 'Bunyodkor 12',
        ]);

        $this->assertStringContainsString(
            'Manzil: Toshkent viloyati, Chilonzor, Bunyodkor 12',
            $text,
        );
    }

    public function test_message_skips_address_line_when_all_fields_empty(): void
    {
        $text = $this->dispatchWithShopOverrides([
            'region' => null,
            'district' => null,
            'address' => null,
        ]);

        $this->assertStringNotContainsString('Manzil:', $text);
    }

    public function test_message_joins_only_present_address_parts(): void
    {
        $text = $this->dispatchWithShopOverrides([
            'region' => 'Surxondaryo',
            'district' => null,
            'address' => 'Termiz markaz',
        ]);

        $this->assertStringContainsString(
            'Manzil: Surxondaryo, Termiz markaz',
            $text,
        );
    }

    public function test_item_line_for_dona_without_pack_shows_unit_only(): void
    {
        $text = $this->dispatchWithItem([
            'product_name' => 'Arabika',
            'price' => 20_000,
            'pack_price' => null,
            'qty' => 9,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ]);

        $this->assertStringContainsString(
            '1. [Arabika]: 9 dona = 180,000 so\'m',
            $text,
        );
    }

    /**
     * @param  array<string, mixed>  $shopAttrs
     */
    private function dispatchWithShopOverrides(array $shopAttrs): string
    {
        return $this->dispatchWithItem([
            'product_name' => 'Test',
            'price' => 1000,
            'pack_price' => null,
            'qty' => 1,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ], $shopAttrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $shopAttrs
     */
    private function dispatchWithItem(array $attrs, array $shopAttrs = []): string
    {
        $dealer = Dealer::factory()->create(['telegram_chat_id' => 12345]);
        $shop = Shop::factory()->for($dealer)->create($shopAttrs);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => 0,
        ]);

        OrderItem::factory()->for($order)->create($attrs);

        $captured = '';
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (string $text = '', ...$rest) use (&$captured) {
                $captured = $text;

                return null;
            });

        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot) {}

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });

        app(SendOrderNotificationToDealer::class)->handle(new OrderCreated($order->fresh()));

        return $captured;
    }
}
