<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\OrderAssignmentException;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private Dealer $dealer;

    private Shop $shop;

    private User $owner;

    private User $warehouse;

    private User $deliveryman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderService::class);
        $this->dealer = Dealer::factory()->create();
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
        $this->warehouse = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::WAREHOUSE]);
        $this->deliveryman = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
    }

    public function test_assemble_transitions_pending_to_assembling_and_records_history(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $updated = $this->service->assemble($order, $this->warehouse);

        $this->assertSame(OrderStatus::ASSEMBLING, $updated->status);
        $this->assertNotNull($updated->assembling_at);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PENDING->value,
            'to_status' => OrderStatus::ASSEMBLING->value,
            'changed_by_user_id' => $this->warehouse->id,
        ]);
    }

    public function test_dispatch_requires_assigned_deliveryman(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->expectException(OrderAssignmentException::class);
        $this->service->dispatch($order, $this->deliveryman);
    }

    public function test_dispatch_transitions_status_regardless_of_caller_when_assignment_exists(): void
    {
        // Service layer caller-rolni tekshirmaydi (bu policy mas'uliyati).
        // Bu yerda faqat assignment guard ishlashini tasdiqlaymiz.
        $other = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);

        $updated = $this->service->dispatch($order, $other);

        $this->assertSame(OrderStatus::DELIVERING, $updated->status);
    }

    public function test_dispatch_succeeds_for_assigned_deliveryman(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);

        $updated = $this->service->dispatch($order, $this->deliveryman);

        $this->assertSame(OrderStatus::DELIVERING, $updated->status);
        $this->assertNotNull($updated->delivering_at);
    }

    public function test_dispatch_succeeds_for_owner_when_assigned(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);

        $updated = $this->service->dispatch($order, $this->owner);

        $this->assertSame(OrderStatus::DELIVERING, $updated->status);
    }

    public function test_cancel_records_actor_and_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $updated = $this->service->cancel($order, $this->owner, 'Tovar tugadi');

        $this->assertSame(OrderStatus::CANCELLED, $updated->status);
        $this->assertNotNull($updated->cancelled_at);
        $this->assertSame($this->owner->id, $updated->cancelled_by_user_id);
        $this->assertSame('Tovar tugadi', $updated->cancellation_reason);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::CANCELLED->value,
            'changed_by_user_id' => $this->owner->id,
            'reason' => 'Tovar tugadi',
        ]);
    }

    public function test_assign_deliveryman_sets_assigned_at(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $updated = $this->service->assignDeliveryman($order, $this->deliveryman->id);

        $this->assertSame($this->deliveryman->id, $updated->deliveryman_id);
        $this->assertNotNull($updated->assigned_at);
    }

    public function test_assign_deliveryman_clears_assignment_when_null(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING, [
            'deliveryman_id' => $this->deliveryman->id,
            'assigned_at' => now(),
        ]);

        $updated = $this->service->assignDeliveryman($order, null);

        $this->assertNull($updated->deliveryman_id);
        $this->assertNull($updated->assigned_at);
    }

    public function test_assign_deliveryman_rejects_non_deliveryman(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->expectException(OrderAssignmentException::class);
        $this->service->assignDeliveryman($order, $this->warehouse->id);
    }

    public function test_assign_deliveryman_rejects_other_dealer(): void
    {
        $other = User::factory()->create([
            'dealer_id' => Dealer::factory()->create()->id,
            'role' => UserRole::DELIVERYMAN,
        ]);
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->expectException(OrderAssignmentException::class);
        $this->service->assignDeliveryman($order, $other->id);
    }

    public function test_assign_deliveryman_locked_after_delivering(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->expectException(OrderAssignmentException::class);
        $this->service->assignDeliveryman($order, null);
    }

    public function test_self_assign_works_for_unassigned_order(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $updated = $this->service->selfAssignDeliveryman($order, $this->deliveryman);

        $this->assertSame($this->deliveryman->id, $updated->deliveryman_id);
    }

    public function test_self_assign_rejects_already_assigned_order(): void
    {
        $other = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
        $order = $this->makeOrder(OrderStatus::PENDING, ['deliveryman_id' => $other->id]);

        $this->expectException(OrderAssignmentException::class);
        $this->service->selfAssignDeliveryman($order, $this->deliveryman);
    }

    public function test_self_assign_rejects_non_deliveryman_actor(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->expectException(OrderAssignmentException::class);
        $this->service->selfAssignDeliveryman($order, $this->warehouse);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeOrder(OrderStatus $status, array $extra = []): Order
    {
        return Order::factory()
            ->for($this->dealer)
            ->for($this->shop)
            ->create(array_merge(['status' => $status, 'total' => 50_000], $extra));
    }
}
