<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrderPolicy $policy;

    private Dealer $dealer;

    private Shop $shop;

    private User $owner;

    private User $warehouse;

    private User $deliveryman;

    private User $otherDealerOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new OrderPolicy;
        $this->dealer = Dealer::factory()->create();
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
        $this->warehouse = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::WAREHOUSE]);
        $this->deliveryman = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);

        $otherDealer = Dealer::factory()->create();
        $this->otherDealerOwner = User::factory()->create(['dealer_id' => $otherDealer->id, 'role' => UserRole::DEALER]);
    }

    public function test_view_allows_all_dealer_staff(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->assertTrue($this->policy->view($this->owner, $order));
        $this->assertTrue($this->policy->view($this->warehouse, $order));
        $this->assertTrue($this->policy->view($this->deliveryman, $order));
    }

    public function test_view_blocks_other_dealers(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->assertFalse($this->policy->view($this->otherDealerOwner, $order));
    }

    public function test_assemble_allowed_for_owner_and_warehouse_at_pending(): void
    {
        $pending = $this->makeOrder(OrderStatus::PENDING);

        $this->assertTrue($this->policy->assemble($this->owner, $pending));
        $this->assertTrue($this->policy->assemble($this->warehouse, $pending));
        $this->assertFalse($this->policy->assemble($this->deliveryman, $pending));
    }

    public function test_assemble_backfill_allowed_when_no_items_picked(): void
    {
        $assembling = $this->makeOrderWithPickedItem(OrderStatus::ASSEMBLING, pickedQty: 0);
        $delivering = $this->makeOrderWithPickedItem(OrderStatus::DELIVERING, pickedQty: 0);

        $this->assertTrue($this->policy->assemble($this->owner, $assembling));
        $this->assertTrue($this->policy->assemble($this->warehouse, $assembling));
        $this->assertTrue($this->policy->assemble($this->owner, $delivering));
        $this->assertFalse($this->policy->assemble($this->deliveryman, $assembling));
    }

    public function test_assemble_denied_when_items_already_picked(): void
    {
        $assembling = $this->makeOrderWithPickedItem(OrderStatus::ASSEMBLING, pickedQty: 5);
        $delivering = $this->makeOrderWithPickedItem(OrderStatus::DELIVERING, pickedQty: 5);
        $delivered = $this->makeOrder(OrderStatus::DELIVERED);

        $this->assertFalse($this->policy->assemble($this->warehouse, $assembling));
        $this->assertFalse($this->policy->assemble($this->warehouse, $delivering));
        $this->assertFalse($this->policy->assemble($this->warehouse, $delivered));
    }

    public function test_dispatch_allowed_for_owner_warehouse_and_assigned_deliveryman(): void
    {
        $assigned = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);
        $unassigned = $this->makeOrder(OrderStatus::ASSEMBLING);

        // Dostavkachi faqat o'ziga biriktirilgan buyurtmani yo'lga chiqaradi
        $this->assertTrue($this->policy->dispatch($this->deliveryman, $assigned));
        $this->assertFalse($this->policy->dispatch($this->deliveryman, $unassigned));

        // Owner har doim ruxsat
        $this->assertTrue($this->policy->dispatch($this->owner, $assigned));
        $this->assertTrue($this->policy->dispatch($this->owner, $unassigned));

        // Warehouse ham ruxsat
        $this->assertTrue($this->policy->dispatch($this->warehouse, $assigned));
        $this->assertTrue($this->policy->dispatch($this->warehouse, $unassigned));
    }

    public function test_deliver_only_at_delivering_status(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->assertTrue($this->policy->deliver($this->deliveryman, $order));

        $assembling = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);
        $this->assertFalse($this->policy->deliver($this->deliveryman, $assembling));
    }

    public function test_cancel_only_at_cancellable_statuses(): void
    {
        foreach ([OrderStatus::PENDING, OrderStatus::ASSEMBLING] as $status) {
            $order = $this->makeOrder($status);
            $this->assertTrue($this->policy->cancel($this->owner, $order), "owner can cancel from {$status->value}");
            $this->assertTrue($this->policy->cancel($this->warehouse, $order), "warehouse can cancel from {$status->value}");
            $this->assertFalse($this->policy->cancel($this->deliveryman, $order), 'deliveryman cannot cancel');
        }

        $delivering = $this->makeOrder(OrderStatus::DELIVERING);
        $this->assertFalse($this->policy->cancel($this->owner, $delivering));
    }

    public function test_release_self_only_for_assigned_deliveryman_before_delivering(): void
    {
        foreach ([OrderStatus::PENDING, OrderStatus::ASSEMBLING] as $status) {
            $assigned = $this->makeOrder($status, ['deliveryman_id' => $this->deliveryman->id]);
            $this->assertTrue(
                $this->policy->releaseSelf($this->deliveryman, $assigned),
                "assigned deliveryman can release from {$status->value}",
            );

            $unassigned = $this->makeOrder($status);
            $this->assertFalse(
                $this->policy->releaseSelf($this->deliveryman, $unassigned),
                'unassigned deliveryman cannot release',
            );

            $this->assertFalse(
                $this->policy->releaseSelf($this->owner, $assigned),
                'owner cannot release self',
            );
            $this->assertFalse(
                $this->policy->releaseSelf($this->warehouse, $assigned),
                'warehouse cannot release self',
            );
        }

        $delivering = $this->makeOrder(OrderStatus::DELIVERING, [
            'deliveryman_id' => $this->deliveryman->id,
        ]);
        $this->assertFalse(
            $this->policy->releaseSelf($this->deliveryman, $delivering),
            'cannot release after delivering',
        );
    }

    public function test_assign_deliveryman_only_for_owner(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->assertTrue($this->policy->assignDeliveryman($this->owner, $order));
        $this->assertFalse(
            $this->policy->assignDeliveryman($this->warehouse, $order),
            'warehouse should not assign deliveryman',
        );
        $this->assertFalse($this->policy->assignDeliveryman($this->deliveryman, $order));

        $delivering = $this->makeOrder(OrderStatus::DELIVERING);
        $this->assertFalse($this->policy->assignDeliveryman($this->owner, $delivering));
    }

    public function test_self_assign_only_for_unassigned_orders_and_deliverymen(): void
    {
        $unassigned = $this->makeOrder(OrderStatus::PENDING);
        $assigned = $this->makeOrder(OrderStatus::PENDING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->assertTrue($this->policy->selfAssign($this->deliveryman, $unassigned));
        $this->assertFalse($this->policy->selfAssign($this->deliveryman, $assigned));
        $this->assertFalse($this->policy->selfAssign($this->owner, $unassigned));
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

    private function makeOrderWithPickedItem(OrderStatus $status, float $pickedQty): Order
    {
        $order = $this->makeOrder($status);
        $product = Product::factory()->for($this->dealer)->create();

        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10_000,
            'qty' => 5,
            'unit' => 'dona',
            'pack_size' => 1,
            'picked_qty' => $pickedQty,
        ]);

        return $order->fresh('items');
    }
}
