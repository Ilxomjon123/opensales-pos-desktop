<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    private Shop $shop;

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
    }

    public function test_guest_cannot_access_orders(): void
    {
        $this->get(route('dealer.orders.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_cannot_access_dealer_orders(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dealer.orders.index'))
            ->assertRedirect(route('admin.dealers.index'));
    }

    public function test_dealer_can_list_orders(): void
    {
        Order::factory()->for($this->dealer)->for($this->shop)->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index'))
            ->assertOk();
    }

    public function test_dealer_can_filter_orders_by_status(): void
    {
        Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::PENDING]);
        Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::DELIVERED]);

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index', ['status' => 'pending']))
            ->assertOk();
    }

    public function test_dealer_can_filter_orders_by_multiple_statuses(): void
    {
        $pending = Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::PENDING]);
        $assembling = Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::ASSEMBLING]);
        $delivering = Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::DELIVERING]);
        Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::DELIVERED]);
        Order::factory()->for($this->dealer)->for($this->shop)->create(['status' => OrderStatus::CANCELLED]);

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index', ['status' => ['pending', 'assembling', 'delivering']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Orders/Index')
                ->where('orders.meta.total', 3)
            );

        $returnedIds = collect([$pending, $assembling, $delivering])->pluck('id')->sort()->values()->all();
        $this->assertCount(3, $returnedIds);
    }

    public function test_dealer_can_search_orders_by_number(): void
    {
        $first = Order::factory()->for($this->dealer)->for($this->shop)->create();
        $second = Order::factory()->for($this->dealer)->for($this->shop)->create();
        $third = Order::factory()->for($this->dealer)->for($this->shop)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index', ['search' => (string) $second->number]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Orders/Index')
                ->where('orders.data.0.id', $second->id)
                ->where('orders.meta.total', 1)
            );

        $this->assertNotSame($first->number, $second->number);
        $this->assertNotSame($second->number, $third->number);
    }

    public function test_search_with_non_numeric_query_returns_empty_result(): void
    {
        Order::factory()->for($this->dealer)->for($this->shop)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index', ['search' => 'abc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('orders.meta.total', 0));
    }

    public function test_deliveryman_sees_orders_sorted_by_created_at_desc(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $otherDeliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $otherOrder = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'deliveryman_id' => $otherDeliveryman->id,
            'created_at' => now()->subDays(2),
        ]);

        $unassignedOrder = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'deliveryman_id' => null,
            'created_at' => now()->subDay(),
        ]);

        $myOrder = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'deliveryman_id' => $deliveryman->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Orders/Index')
                ->where('orders.data.0.id', $unassignedOrder->id)
                ->where('orders.data.1.id', $otherOrder->id)
                ->where('orders.data.2.id', $myOrder->id)
            );
    }

    public function test_orders_index_flags_pending_return_only_for_delivered_with_leftover(): void
    {
        // Carry qty bo'yicha — picked > delivered + returned.
        $deliveredWithQtyCarry = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->for($deliveredWithQtyCarry)->create([
            'qty' => 10,
            'picked_qty' => 10,
            'delivered_qty' => 6,
            'returned_qty' => 0,
        ]);

        // Carry pack bo'yicha — picked_pack > delivered_pack + returned_pack.
        $deliveredWithPackCarry = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->for($deliveredWithPackCarry)->create([
            'qty' => 24,
            'pack_qty' => 6,
            'pack_size' => 4,
            'picked_qty' => 24,
            'picked_pack_qty' => 6,
            'delivered_qty' => 24,
            'delivered_pack_qty' => 4,
            'returned_qty' => 0,
            'returned_pack_qty' => 0,
        ]);

        // To'liq yetkazilgan — carry yo'q. SQL precedence bug bo'lsa bu ham
        // boshqa orderlardagi pack carry sababli xato true qaytaradi.
        $deliveredClean = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->for($deliveredClean)->create([
            'qty' => 20,
            'pack_qty' => 5,
            'pack_size' => 4,
            'picked_qty' => 20,
            'picked_pack_qty' => 5,
            'delivered_qty' => 20,
            'delivered_pack_qty' => 5,
            'returned_qty' => 0,
            'returned_pack_qty' => 0,
        ]);

        // Status filter — pending orderda picked > delivered bo'lsa ham false.
        $pending = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::PENDING,
        ]);
        OrderItem::factory()->for($pending)->create([
            'qty' => 4,
            'picked_qty' => 4,
            'delivered_qty' => 0,
            'returned_qty' => 0,
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Orders/Index')
                ->where('orders.data', function ($rows) use ($deliveredWithQtyCarry, $deliveredWithPackCarry, $deliveredClean, $pending) {
                    $byId = collect($rows)->keyBy('id');

                    return $byId[$deliveredWithQtyCarry->id]['has_pending_return'] === true
                        && $byId[$deliveredWithPackCarry->id]['has_pending_return'] === true
                        && $byId[$deliveredClean->id]['has_pending_return'] === false
                        && $byId[$pending->id]['has_pending_return'] === false;
                })
            );
    }

    public function test_dealer_can_view_own_order(): void
    {
        $order = Order::factory()->for($this->dealer)->for($this->shop)->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.orders.show', $order))
            ->assertOk();
    }

    public function test_dealer_cannot_view_others_order(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();
        $order = Order::factory()->for($otherDealer)->for($otherShop)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.orders.show', $order))
            ->assertForbidden();
    }

    public function test_dealer_can_update_order_status(): void
    {
        Event::fake([OrderCreated::class]);

        $order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::PENDING,
        ]);

        $this->actingAs($this->user)
            ->put(route('dealer.orders.update', $order), ['status' => 'assembling'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::ASSEMBLING, $order->fresh()->status);
    }

    public function test_dealer_cannot_make_invalid_transition(): void
    {
        $order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::DELIVERED,
        ]);

        $this->actingAs($this->user)
            ->from(route('dealer.orders.show', $order))
            ->put(route('dealer.orders.update', $order), ['status' => 'pending'])
            ->assertRedirect(route('dealer.orders.show', $order))
            ->assertSessionHasErrors('error');
    }

    public function test_dealer_can_open_create_order_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('dealer.orders.create'))
            ->assertOk();
    }

    public function test_deliveryman_cannot_open_create_order_page(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.orders.create'))
            ->assertForbidden();
    }

    public function test_dealer_can_create_order_from_panel(): void
    {
        Event::fake([OrderCreated::class]);

        $product = Product::factory()->for($this->dealer)->create([
            'price' => 50_000,
            'stock' => 100,
            'has_types' => false,
            'bulk_only' => false,
            'pack_size' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('dealer.orders.store'), [
                'shop_id' => $this->shop->id,
                'note' => 'Test izoh',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 3, 'price' => 50_000],
                ],
            ])
            ->assertRedirect();

        $order = Order::query()->where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(150_000, $order->total);
        $this->assertSame('Test izoh', $order->note);
        $this->assertSame(3, (int) (int) $order->items()->first()->qty);

        Event::assertDispatched(OrderCreated::class);
    }

    public function test_create_order_rejects_product_from_other_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreignProduct = Product::factory()->for($otherDealer)->create([
            'price' => 10_000,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('dealer.orders.create'))
            ->post(route('dealer.orders.store'), [
                'shop_id' => $this->shop->id,
                'items' => [
                    ['product_id' => $foreignProduct->id, 'qty' => 1],
                ],
            ])
            ->assertRedirect(route('dealer.orders.create'))
            ->assertSessionHasErrors('items.0.product_id');
    }

    public function test_create_order_rejects_shop_from_other_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreignShop = Shop::factory()->for($otherDealer)->create();
        $product = Product::factory()->for($this->dealer)->create([
            'price' => 50_000,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('dealer.orders.create'))
            ->post(route('dealer.orders.store'), [
                'shop_id' => $foreignShop->id,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 1],
                ],
            ])
            ->assertRedirect(route('dealer.orders.create'))
            ->assertSessionHasErrors('shop_id');
    }
}
