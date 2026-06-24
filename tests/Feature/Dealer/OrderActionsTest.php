<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private Shop $shop;

    private User $owner;

    private User $warehouse;

    private User $deliveryman;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);

        $this->dealer = Dealer::factory()->create();
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
        $this->warehouse = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::WAREHOUSE]);
        $this->deliveryman = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
    }

    public function test_warehouse_can_assemble_pending_order(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->warehouse)
            ->post(route('dealer.orders.assemble', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);
    }

    public function test_deliveryman_cannot_assemble(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.assemble', $order))
            ->assertForbidden();
    }

    public function test_assigned_deliveryman_can_dispatch_own_order(): void
    {
        // Yangi policy: dispatch o'ziga biriktirilgan dostavkachi uchun ham ochiq —
        // sklad/owner tovarni assemble paytida chiqaradi, dostavkachi
        // "Yo'lga chiqish" tugmasi bilan status flip qiladi.
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.dispatch', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::DELIVERING, $order->fresh()->status);
    }

    public function test_deliveryman_cannot_dispatch_other_orders(): void
    {
        $other = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $other->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.dispatch', $order))
            ->assertForbidden();

        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);
    }

    public function test_warehouse_can_dispatch_assigned_order(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->warehouse)
            ->post(route('dealer.orders.dispatch', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::DELIVERING, $order->fresh()->status);
    }

    public function test_owner_can_dispatch_with_deliveryman_in_one_step(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.dispatch', $order), [
                'deliveryman_id' => $this->deliveryman->id,
            ])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::DELIVERING, $fresh->status);
        $this->assertSame($this->deliveryman->id, $fresh->deliveryman_id);
        $this->assertNotNull($fresh->assigned_at);
    }

    public function test_dispatch_validates_deliveryman_role(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->owner)
            ->from(route('dealer.orders.show', $order))
            ->post(route('dealer.orders.dispatch', $order), [
                'deliveryman_id' => $this->warehouse->id,
            ])
            ->assertSessionHasErrors('deliveryman_id');

        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);
    }

    public function test_owner_can_cancel_with_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.cancel', $order), ['reason' => 'Tovar tugadi'])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $fresh->status);
        $this->assertSame('Tovar tugadi', $fresh->cancellation_reason);
        $this->assertSame($this->owner->id, $fresh->cancelled_by_user_id);
    }

    public function test_cancel_requires_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->owner)
            ->from(route('dealer.orders.show', $order))
            ->post(route('dealer.orders.cancel', $order), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_owner_can_assign_deliveryman(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->owner)
            ->patch(route('dealer.orders.deliveryman.assign', $order), [
                'deliveryman_id' => $this->deliveryman->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->deliveryman->id, $order->fresh()->deliveryman_id);
    }

    public function test_assign_deliveryman_validates_role(): void
    {
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->owner)
            ->from(route('dealer.orders.show', $order))
            ->patch(route('dealer.orders.deliveryman.assign', $order), [
                'deliveryman_id' => $this->warehouse->id,
            ])
            ->assertSessionHasErrors('deliveryman_id');
    }

    public function test_deliveryman_can_self_assign_unassigned_order(): void
    {
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.self-assign', $order))
            ->assertRedirect();

        $this->assertSame($this->deliveryman->id, $order->fresh()->deliveryman_id);
    }

    public function test_deliveryman_cannot_self_assign_already_taken_order(): void
    {
        $other = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);
        $order = $this->makeOrder(OrderStatus::PENDING, ['deliveryman_id' => $other->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.self-assign', $order))
            ->assertForbidden();
    }

    public function test_assigned_deliveryman_can_deliver(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERING, ['deliveryman_id' => $this->deliveryman->id]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.deliver', $order), [
                'items' => [],
                'paid_amount' => 0,
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_cash_paid_on_delivery_is_tagged_with_deliveryman(): void
    {
        $product = Product::factory()
            ->for($this->dealer)
            ->create(['stock' => 100, 'price' => 50_000]);

        $order = $this->makeOrder(OrderStatus::DELIVERING, ['deliveryman_id' => $this->deliveryman->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 50_000,
            'qty' => 2,
            'unit' => 'dona',
            'pack_size' => 1,
            'picked_qty' => 2,
        ]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.deliver', $order), [
                'items' => [
                    ['product_id' => $product->id, 'delivered_qty' => 2],
                ],
                'paid_amount' => 100_000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'type' => 'credit',
            'method' => 'cash',
            'amount' => 100_000,
            'deliveryman_id' => $this->deliveryman->id,
        ]);
    }

    public function test_card_paid_on_delivery_does_not_tag_deliveryman(): void
    {
        $product = Product::factory()
            ->for($this->dealer)
            ->create(['stock' => 100, 'price' => 50_000]);

        $order = $this->makeOrder(OrderStatus::DELIVERING, ['deliveryman_id' => $this->deliveryman->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 50_000,
            'qty' => 1,
            'unit' => 'dona',
            'pack_size' => 1,
            'picked_qty' => 1,
        ]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.orders.deliver', $order), [
                'items' => [
                    ['product_id' => $product->id, 'delivered_qty' => 1],
                ],
                'paid_amount' => 50_000,
                'paid_card' => 50_000,
                'cardholder_name' => 'Ali Valiyev',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'card',
            'amount' => 50_000,
            'deliveryman_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function test_owner_can_edit_picked_in_assembling(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10]);
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.picked.edit', $order), [
                'items' => [['product_id' => $product->id, 'picked_qty' => 4]],
            ])
            ->assertRedirect();

        $this->assertSame(4.0, (float) $order->fresh()->items()->first()->picked_qty);
        $this->assertSame(6, (int) $product->fresh()->stock);
    }

    public function test_warehouse_cannot_edit_picked(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10]);
        $order = $this->makeOrder(OrderStatus::ASSEMBLING);

        $this->actingAs($this->warehouse)
            ->post(route('dealer.orders.picked.edit', $order), [
                'items' => [['product_id' => $product->id, 'picked_qty' => 4]],
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_edit_picked_in_pending(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10]);
        $order = $this->makeOrder(OrderStatus::PENDING);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.picked.edit', $order), [
                'items' => [['product_id' => $product->id, 'picked_qty' => 4]],
            ])
            ->assertForbidden();
    }

    private function makeOrder(OrderStatus $status, array $extra = []): Order
    {
        return Order::factory()
            ->for($this->dealer)
            ->for($this->shop)
            ->create(array_merge(['status' => $status, 'total' => 50_000], $extra));
    }
}
