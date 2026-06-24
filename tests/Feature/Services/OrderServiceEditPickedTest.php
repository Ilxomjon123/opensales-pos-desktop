<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class OrderServiceEditPickedTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private Dealer $dealer;

    private Shop $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
        $this->dealer = Dealer::factory()->create();
        $this->shop = Shop::factory()->for($this->dealer)->create(['balance' => 0]);
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
    }

    public function test_increasing_picked_qty_reduces_stock_by_delta(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedAssemblingOrder($product, qty: 5, picked: 5);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 8]],
            by: $this->owner,
        );

        // 10 - 5 (seed pick) - 3 (edit delta) = 2
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame(8.0, (float) $order->items()->first()->picked_qty);
    }

    public function test_decreasing_picked_qty_restores_stock(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedAssemblingOrder($product, qty: 5, picked: 5);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 2]],
            by: $this->owner,
        );

        // 10 - 5 (seed pick) + 3 (edit delta back) = 8
        $this->assertSame(8, (int) $product->fresh()->stock);
        $this->assertSame(2.0, (float) $order->items()->first()->picked_qty);
    }

    public function test_editing_picked_does_not_touch_balance_or_payments(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedAssemblingOrder($product, qty: 5, picked: 5);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 8]],
            by: $this->owner,
        );

        $this->assertSame(0, (int) $this->shop->fresh()->balance);
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
    }

    public function test_adding_new_product_picks_from_stock(): void
    {
        $a = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $b = Product::factory()->for($this->dealer)->create(['price' => 5_000, 'stock' => 20]);
        $order = $this->seedAssemblingOrder($a, qty: 5, picked: 5);

        $this->service->editPicked(
            order: $order,
            items: [
                ['product_id' => $a->id, 'picked_qty' => 5],
                ['product_id' => $b->id, 'picked_qty' => 4],
            ],
            by: $this->owner,
        );

        $this->assertSame(5, (int) $a->fresh()->stock);
        $this->assertSame(16, (int) $b->fresh()->stock);
        $this->assertCount(2, $order->fresh()->items);
    }

    public function test_zeroing_picked_qty_restores_full_stock(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedAssemblingOrder($product, qty: 5, picked: 5);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 0]],
            by: $this->owner,
        );

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(0.0, (float) $order->items()->first()->picked_qty);
    }

    public function test_works_in_delivering_status(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedAssemblingOrder($product, qty: 5, picked: 5, status: OrderStatus::DELIVERING);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 7]],
            by: $this->owner,
        );

        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_rejects_non_assembling_or_delivering_status(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::PENDING,
            'total' => 50_000,
        ]);
        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => $product->name,
            'price' => 10_000,
            'qty' => 5,
            'picked_qty' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->editPicked(
            order: $order,
            items: [['product_id' => $product->id, 'picked_qty' => 5]],
            by: $this->owner,
        );
    }

    private function seedAssemblingOrder(Product $product, int $qty, int $picked, OrderStatus $status = OrderStatus::ASSEMBLING): Order
    {
        $order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => $status,
            'total' => $product->price * $qty,
            'assembling_at' => now(),
        ]);

        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => $product->name,
            'price' => $product->price,
            'qty' => $qty,
            'picked_qty' => $picked,
            'delivered_qty' => 0,
        ]);

        $product->decrement('stock', $picked);

        return $order->fresh();
    }
}
