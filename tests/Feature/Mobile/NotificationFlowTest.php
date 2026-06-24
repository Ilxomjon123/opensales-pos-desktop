<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\BroadcastAudienceType;
use App\Enums\OrderStatus;
use App\Events\ProductPriceChanged;
use App\Jobs\SendBroadcastAppNotificationJob;
use App\Listeners\NotifyPriceChangeApp;
use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\Broadcast\BroadcastAudienceResolver;
use App\Services\Broadcast\BroadcastRenderer;
use App\Services\NotificationService;
use App\Services\OrderMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_creates_feed_row_for_linked_customer_in_their_locale(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $customer = Customer::factory()->create(['locale' => 'ru']);
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
        $order = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);

        app(NotificationService::class)->orderStatus($order, OrderStatus::DELIVERING);

        $notif = AppNotification::query()->where('customer_id', $customer->id)->first();

        $this->assertNotNull($notif);
        $this->assertSame('order_status', $notif->type);
        $this->assertSame($order->id, $notif->order_id);
        $this->assertStringContainsString('Заказ', $notif->title);          // ru locale
        $this->assertSame((string) $order->id, $notif->data['order_id']);
        $this->assertSame((string) $dealer->id, $notif->data['dealer_id']);
    }

    public function test_member_without_customer_gets_no_feed_row(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => null]);
        $order = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);

        app(NotificationService::class)->orderCreated($order);

        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_feed_lists_notifications_with_unread_count_and_marks_read(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        AppNotification::query()->create([
            'customer_id' => $customer->id,
            'type' => 'order_status',
            'title' => 'A',
            'body' => 'a',
        ]);
        $read = AppNotification::query()->create([
            'customer_id' => $customer->id,
            'type' => 'order_status',
            'title' => 'B',
            'body' => 'b',
            'read_at' => now(),
        ]);
        // Boshqa customer'niki — ko'rinmasligi kerak.
        AppNotification::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'type' => 'order_status',
            'title' => 'C',
            'body' => 'c',
        ]);

        $this->getJson('/api/mobile/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'data');

        $unread = AppNotification::query()
            ->where('customer_id', $customer->id)->whereNull('read_at')->first();

        $this->postJson("/api/mobile/notifications/{$unread->id}/read")->assertOk();
        $this->getJson('/api/mobile/notifications')->assertJsonPath('unread_count', 0);

        // Idempotent: allaqachon o'qilgan.
        $this->assertNotNull($read->fresh()->read_at);
    }

    public function test_read_all_marks_every_unread(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        foreach (range(1, 3) as $i) {
            AppNotification::query()->create([
                'customer_id' => $customer->id,
                'type' => 'order_status',
                'title' => "T{$i}",
                'body' => 'x',
            ]);
        }

        $this->postJson('/api/mobile/notifications/read-all')->assertOk();
        $this->assertSame(0, AppNotification::query()->forCustomer($customer->id)->unread()->count());
    }

    public function test_read_by_context_marks_order_notifications_read(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $orderA = Order::factory()->create();
        $orderB = Order::factory()->create();

        $n1 = AppNotification::query()->create([
            'customer_id' => $customer->id, 'order_id' => $orderA->id,
            'type' => 'order_status', 'title' => 'A', 'body' => 'a',
        ]);
        // Boshqa buyurtma — tegmasligi kerak.
        $other = AppNotification::query()->create([
            'customer_id' => $customer->id, 'order_id' => $orderB->id,
            'type' => 'order_status', 'title' => 'B', 'body' => 'b',
        ]);

        $this->postJson('/api/mobile/notifications/read-context', ['order_id' => $orderA->id])
            ->assertOk();

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_cannot_read_another_customers_notification(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $foreign = AppNotification::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'type' => 'order_status',
            'title' => 'X',
            'body' => 'x',
        ]);

        $this->postJson("/api/mobile/notifications/{$foreign->id}/read")->assertNotFound();
    }

    public function test_deleting_order_message_removes_its_feed_notification(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $customer = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
        $order = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);

        $service = app(OrderMessageService::class);
        $message = $service->create($order, null, 'Salom');

        $this->assertSame(1, AppNotification::query()
            ->where('type', 'order_message')
            ->where('data->message_id', (string) $message->id)
            ->count());

        $service->delete($message);

        $this->assertSame(0, AppNotification::query()
            ->where('type', 'order_message')
            ->where('data->message_id', (string) $message->id)
            ->count());
    }

    public function test_new_product_broadcasts_feed_to_all_dealer_customers(): void
    {
        $dealer = Dealer::factory()->create(['notify_on_new_product' => true]);
        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();
        $c1 = Customer::factory()->create();
        $c2 = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $shopA->id, 'customer_id' => $c1->id]);
        ShopMember::factory()->create(['shop_id' => $shopB->id, 'customer_id' => $c2->id]);

        $product = Product::factory()->for($dealer)->create([
            'is_active' => true,
            'price' => 12000,
        ]);

        app(NotificationService::class)->productCreated($product);

        $this->assertSame(1, AppNotification::query()->forCustomer($c1->id)
            ->where('type', 'product_new')->count());
        $this->assertSame(1, AppNotification::query()->forCustomer($c2->id)
            ->where('type', 'product_new')->count());

        $notif = AppNotification::query()->forCustomer($c1->id)->first();
        $this->assertSame((string) $product->id, $notif->data['product_id']);
        $this->assertSame((string) $dealer->id, $notif->data['dealer_id']);
    }

    public function test_price_change_listener_respects_dealer_flag(): void
    {
        $dealer = Dealer::factory()->create(['notify_on_price_change' => false]);
        $shop = Shop::factory()->for($dealer)->create();
        $customer = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
        $product = Product::factory()->for($dealer)->create(['is_active' => true]);

        (new NotifyPriceChangeApp(app(NotificationService::class)))->handle(
            new ProductPriceChanged($product, 100.0, null, 200.0, null),
        );

        $this->assertSame(0, AppNotification::query()->where('type', 'product_price')->count());
    }

    public function test_broadcast_job_creates_feed_for_dealer_customers(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $customer = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
        // Telegram-only a'zo (customer_id yo'q) — mobil feed olmaydi.
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => null]);

        (new SendBroadcastAppNotificationJob(
            dealerId: $dealer->id,
            audienceType: BroadcastAudienceType::ALL_ACTIVE->value,
            config: [],
            message: 'Aksiya boshlandi!',
            title: $dealer->name,
        ))->handle(
            app(BroadcastAudienceResolver::class),
            app(BroadcastRenderer::class),
            app(NotificationService::class),
        );

        $rows = AppNotification::query()->where('type', 'broadcast')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($customer->id, $rows->first()->customer_id);
        $this->assertStringContainsString('Aksiya', $rows->first()->body);
    }

    public function test_device_token_register_and_remove(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/mobile/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'customer_id' => $customer->id,
            'token' => 'fcm-token-123',
        ]);

        // Qayta yuborilsa — dublikat emas (updateOrCreate).
        $this->postJson('/api/mobile/device-tokens', ['token' => 'fcm-token-123'])->assertOk();
        $this->assertSame(1, DeviceToken::query()->where('token', 'fcm-token-123')->count());

        $this->deleteJson('/api/mobile/device-tokens', ['token' => 'fcm-token-123'])->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-123']);
    }
}
