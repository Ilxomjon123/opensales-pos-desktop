<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentType;
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
use Tests\TestCase;

final class OrderServiceEditTest extends TestCase
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

    public function test_edit_price_change_recomputes_balance(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 12_000, 'delivered_qty' => 5]],
            paidAmount: 60_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(60_000, $order->delivered_total);
        $this->assertSame(60_000, $order->paid_amount);
        $this->assertSame(0, $this->shop->fresh()->balance);
        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_edit_increases_delivered_qty_and_reduces_stock(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 10_000, 'delivered_qty' => 8]],
            paidAmount: 50_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(80_000, $order->delivered_total);
        $this->assertSame(-30_000, $this->shop->fresh()->balance);
        // initial 10 -5 (seed) -3 (edit delta) = 2
        $this->assertSame(2, (int) $product->fresh()->stock);
    }

    public function test_edit_decreases_delivered_qty_and_restores_stock(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 10_000, 'delivered_qty' => 3]],
            paidAmount: 50_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(30_000, $order->delivered_total);
        $this->assertSame(20_000, $this->shop->fresh()->balance);
        // initial 10 -5 (seed) +2 (edit delta) = 7
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_edit_adds_new_product(): void
    {
        $a = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $b = Product::factory()->for($this->dealer)->create(['price' => 5_000, 'stock' => 20]);
        $order = $this->seedDeliveredOrder($a, qty: 5, price: 10_000, paid: 50_000);

        $this->service->edit(
            order: $order,
            items: [
                ['product_id' => $a->id, 'price' => 10_000, 'delivered_qty' => 5],
                ['product_id' => $b->id, 'price' => 5_000, 'delivered_qty' => 4],
            ],
            paidAmount: 50_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(70_000, $order->delivered_total);
        $this->assertSame(-20_000, $this->shop->fresh()->balance);
        $this->assertSame(5, (int) $a->fresh()->stock);
        $this->assertSame(16, (int) $b->fresh()->stock);
        $this->assertCount(2, $order->fresh()->items);
    }

    public function test_edit_removes_product_and_restores_stock(): void
    {
        $a = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $b = Product::factory()->for($this->dealer)->create(['price' => 5_000, 'stock' => 20]);
        $order = $this->seedDeliveredOrderMulti([
            ['product' => $a, 'qty' => 5, 'price' => 10_000],
            ['product' => $b, 'qty' => 4, 'price' => 5_000],
        ], paid: 70_000);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $a->id, 'price' => 10_000, 'delivered_qty' => 5]],
            paidAmount: 70_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(50_000, $order->delivered_total);
        $this->assertSame(20_000, $this->shop->fresh()->balance);
        $this->assertSame(5, (int) $a->fresh()->stock);
        $this->assertSame(20, (int) $b->fresh()->stock);
        $this->assertCount(1, $order->fresh()->items);
    }

    public function test_edit_applies_discount(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 10_000, 'delivered_qty' => 5]],
            paidAmount: 40_000,
            discount: 10_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(50_000, $order->delivered_total);
        $this->assertSame(10_000, (int) $order->discount);
        $this->assertSame(0, $this->shop->fresh()->balance);
    }

    public function test_edit_replaces_old_payment_rows(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);

        $oldPaymentCount = Payment::query()->where('order_id', $order->id)->count();
        $this->assertSame(2, $oldPaymentCount);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 12_000, 'delivered_qty' => 5]],
            paidAmount: 60_000,
            by: $this->owner,
        );

        $payments = Payment::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $payments);
        $this->assertSame(60_000, (int) $payments->where('type', PaymentType::DEBIT)->first()->amount);
        $this->assertSame(60_000, (int) $payments->where('type', PaymentType::CREDIT)->first()->amount);
    }

    public function test_edit_rejects_non_delivered_status(): void
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
            'delivered_qty' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 10_000, 'delivered_qty' => 5]],
            paidAmount: 0,
            by: $this->owner,
        );
    }

    public function test_edit_works_on_received_orders(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->seedDeliveredOrder($product, qty: 5, price: 10_000, paid: 50_000);
        $order->update(['status' => OrderStatus::RECEIVED, 'received_at' => now()]);

        $this->service->edit(
            order: $order,
            items: [['product_id' => $product->id, 'price' => 11_000, 'delivered_qty' => 5]],
            paidAmount: 55_000,
            by: $this->owner,
        );

        $order->refresh();
        $this->assertSame(OrderStatus::RECEIVED, $order->status);
        $this->assertSame(55_000, $order->delivered_total);
        $this->assertSame(0, $this->shop->fresh()->balance);
    }

    private function seedDeliveredOrder(Product $product, int $qty, int $price, int $paid): Order
    {
        return $this->seedDeliveredOrderMulti(
            [['product' => $product, 'qty' => $qty, 'price' => $price]],
            paid: $paid,
        );
    }

    /**
     * @param  array<int, array{product: Product, qty: int|float, price: int}>  $items
     */
    private function seedDeliveredOrderMulti(array $items, int $paid): Order
    {
        $total = (int) array_sum(array_map(static fn (array $r): int => $r['price'] * (int) $r['qty'], $items));

        $order = Order::factory()->for($this->dealer)->for($this->shop)->create([
            'status' => OrderStatus::DELIVERED,
            'total' => $total,
            'delivered_total' => $total,
            'paid_amount' => $paid,
            'delivered_at' => now(),
        ]);

        foreach ($items as $row) {
            OrderItem::factory()->for($order)->for($row['product'])->create([
                'product_name' => $row['product']->name,
                'price' => $row['price'],
                'qty' => $row['qty'],
                'delivered_qty' => $row['qty'],
                'picked_qty' => $row['qty'],
            ]);

            $row['product']->decrement('stock', (int) $row['qty']);
        }

        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'order_id' => $order->id,
            'amount' => $total,
            'type' => PaymentType::DEBIT,
            'method' => 'cash',
            'note' => "Buyurtma #{$order->id}",
        ]);

        if ($paid > 0) {
            Payment::query()->create([
                'shop_id' => $this->shop->id,
                'dealer_id' => $this->dealer->id,
                'order_id' => $order->id,
                'amount' => $paid,
                'type' => PaymentType::CREDIT,
                'method' => 'cash',
                'note' => "Buyurtma #{$order->id} to'lov (naqd)",
            ]);
        }

        $this->shop->update(['balance' => $paid - $total]);
        $this->shop->refresh();

        return $order->fresh();
    }
}
