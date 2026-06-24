<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class RouteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private Shop $shop;

    private User $owner;

    private User $deliveryman;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);

        $this->dealer = Dealer::factory()->create();
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
        $this->deliveryman = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
    }

    public function test_start_route_dispatches_assembling_orders_of_deliveryman(): void
    {
        $a = $this->makeAssembling(['deliveryman_id' => $this->deliveryman->id]);
        $b = $this->makeAssembling(['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.routes.today.dispatch'), [
                'order_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::DELIVERING, $a->fresh()->status);
        $this->assertSame(OrderStatus::DELIVERING, $b->fresh()->status);
        $this->assertNotNull($a->fresh()->delivering_at);
    }

    public function test_start_route_only_dispatches_selected_orders(): void
    {
        $selected = $this->makeAssembling(['deliveryman_id' => $this->deliveryman->id]);
        $unselected = $this->makeAssembling(['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.routes.today.dispatch'), [
                'order_ids' => [$selected->id],
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::DELIVERING, $selected->fresh()->status);
        $this->assertSame(OrderStatus::ASSEMBLING, $unselected->fresh()->status);
    }

    public function test_start_route_ignores_orders_not_assigned_to_deliveryman(): void
    {
        $other = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
        $foreign = $this->makeAssembling(['deliveryman_id' => $other->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.routes.today.dispatch'), [
                'order_ids' => [$foreign->id],
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::ASSEMBLING, $foreign->fresh()->status);
    }

    public function test_start_route_forbidden_for_non_deliveryman(): void
    {
        $order = $this->makeAssembling(['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->owner)
            ->post(route('dealer.routes.today.dispatch'), [
                'order_ids' => [$order->id],
            ])
            ->assertForbidden();

        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);
    }

    public function test_start_route_requires_order_ids(): void
    {
        $this->actingAs($this->deliveryman)
            ->from(route('dealer.routes.today'))
            ->post(route('dealer.routes.today.dispatch'), [])
            ->assertSessionHasErrors('order_ids');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeAssembling(array $extra = []): Order
    {
        return Order::factory()
            ->for($this->dealer)
            ->for($this->shop)
            ->create(array_merge(['status' => OrderStatus::ASSEMBLING, 'total' => 50_000], $extra));
    }
}
