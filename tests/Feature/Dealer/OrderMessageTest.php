<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Jobs\DeleteOrderMessageJob;
use App\Jobs\EditOrderMessageJob;
use App\Jobs\SendOrderMessageJob;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class OrderMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    private Shop $shop;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $member = ShopMember::factory()->for($this->shop)->create(['telegram_id' => 555]);
        $this->order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'member_id' => $member->id,
        ]);
    }

    public function test_dealer_can_post_message_and_it_dispatches_send_job(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post("/dealer/orders/{$this->order->id}/messages", ['body' => 'Tovar yo\'lda'])
            ->assertRedirect();

        $this->assertDatabaseHas('order_messages', [
            'order_id' => $this->order->id,
            'dealer_id' => $this->dealer->id,
            'author_user_id' => $this->user->id,
            'body' => 'Tovar yo\'lda',
        ]);

        Queue::assertPushed(SendOrderMessageJob::class, 1);
    }

    public function test_message_requires_body(): void
    {
        $this->actingAs($this->user)
            ->post("/dealer/orders/{$this->order->id}/messages", ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_dealer_can_edit_message_and_telegram_edit_is_dispatched(): void
    {
        Queue::fake();

        $message = OrderMessage::query()->create([
            'order_id' => $this->order->id,
            'dealer_id' => $this->dealer->id,
            'author_user_id' => $this->user->id,
            'body' => 'Eski',
            'telegram_chat_id' => 555,
            'telegram_message_id' => 999,
        ]);

        $this->actingAs($this->user)
            ->put("/dealer/orders/{$this->order->id}/messages/{$message->id}", ['body' => 'Yangi'])
            ->assertRedirect();

        $this->assertDatabaseHas('order_messages', ['id' => $message->id, 'body' => 'Yangi']);
        Queue::assertPushed(EditOrderMessageJob::class, 1);
    }

    public function test_edit_without_telegram_id_does_not_dispatch(): void
    {
        Queue::fake();

        $message = OrderMessage::query()->create([
            'order_id' => $this->order->id,
            'dealer_id' => $this->dealer->id,
            'author_user_id' => $this->user->id,
            'body' => 'Eski',
        ]);

        $this->actingAs($this->user)
            ->put("/dealer/orders/{$this->order->id}/messages/{$message->id}", ['body' => 'Yangi'])
            ->assertRedirect();

        Queue::assertNotPushed(EditOrderMessageJob::class);
    }

    public function test_dealer_can_delete_message_and_telegram_delete_is_dispatched(): void
    {
        Queue::fake();

        $message = OrderMessage::query()->create([
            'order_id' => $this->order->id,
            'dealer_id' => $this->dealer->id,
            'author_user_id' => $this->user->id,
            'body' => 'O\'chiriladi',
            'telegram_chat_id' => 555,
            'telegram_message_id' => 999,
        ]);

        $this->actingAs($this->user)
            ->delete("/dealer/orders/{$this->order->id}/messages/{$message->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('order_messages', ['id' => $message->id]);
        Queue::assertPushed(DeleteOrderMessageJob::class, 1);
    }

    public function test_cannot_touch_message_of_other_order(): void
    {
        $otherOrder = Order::factory()->for($this->dealer)->for($this->shop)->create();
        $message = OrderMessage::query()->create([
            'order_id' => $otherOrder->id,
            'dealer_id' => $this->dealer->id,
            'body' => 'Boshqa',
        ]);

        $this->actingAs($this->user)
            ->delete("/dealer/orders/{$this->order->id}/messages/{$message->id}")
            ->assertNotFound();
    }

    public function test_miniapp_order_detail_returns_messages(): void
    {
        OrderMessage::query()->create([
            'order_id' => $this->order->id,
            'dealer_id' => $this->dealer->id,
            'body' => 'Mini app xabari',
        ]);

        $this->getJson("/api/miniapp/{$this->dealer->id}/orders/{$this->order->id}?dev_telegram_id=555")
            ->assertOk()
            ->assertJsonFragment(['body' => 'Mini app xabari']);
    }
}
