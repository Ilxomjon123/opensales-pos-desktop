<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusChangeToShop;
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

final class SendOrderStatusChangeToShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivering_status_jami_uses_prepared_total(): void
    {
        // DELIVERING'da "Jami" sklad tayyorlagan miqdor (picked_qty) bo'yicha.
        $captured = $this->dispatchStatusChange(OrderStatus::ASSEMBLING, OrderStatus::DELIVERING);

        $this->assertStringContainsString('Jami: 570,000 so\'m', $captured);
        $this->assertStringNotContainsString('612,000', $captured);
    }

    public function test_cancelled_status_refund_uses_live_items_sum(): void
    {
        $captured = $this->dispatchStatusChange(OrderStatus::PENDING, OrderStatus::CANCELLED);

        $this->assertStringContainsString('Saldoga qaytarildi: 570,000 so\'m', $captured);
        $this->assertStringNotContainsString('612,000', $captured);
    }

    private function dispatchStatusChange(OrderStatus $from, OrderStatus $to): string
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 1_360_121_104,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => $to,
            'total' => 612_000,
        ]);

        // DELIVERING'ga o'tgan zakas — picked_qty/picked_pack_qty
        // ordered qty bilan bir xil (to'liq tayyorlangan).
        $isPostAssembly = in_array($to, [OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED], true);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Hordiq',
            'price' => 5_000,
            'pack_price' => null,
            'qty' => 6,
            'picked_qty' => $isPostAssembly ? 6 : null,
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Mentos',
            'price' => 4_500,
            'pack_price' => 108_000,
            'qty' => 96,
            'picked_qty' => $isPostAssembly ? 96 : null,
            'picked_pack_qty' => $isPostAssembly ? 4 : null,
            'unit' => ProductUnit::DONA,
            'pack_size' => 24,
            'pack_qty' => 4,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Styx',
            'price' => 1_500,
            'pack_price' => null,
            'qty' => 72,
            'picked_qty' => $isPostAssembly ? 72 : null,
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

        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot) {}

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });

        app(SendOrderStatusChangeToShop::class)->handle(new OrderStatusChanged($order, $from, $to));

        return $captured;
    }
}
