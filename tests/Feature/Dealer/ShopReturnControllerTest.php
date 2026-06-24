<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnReason;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->shop = Shop::factory()->for($this->dealer)->create(['balance' => -100_000]);
    }

    public function test_restock_disposition_increases_stock_and_credits_shop(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = $this->makeDeliveredOrder($product, deliveredQty: 4);
        $item = $order->items()->first();

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'note' => 'sifati buzilgan',
                'items' => [[
                    'order_item_id' => $item->id,
                    'qty' => 2,
                    'disposition' => ReturnDisposition::RESTOCK->value,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(12, (int) $product->fresh()->stock);
        $this->assertSame(-100_000 + 2 * 5_000, $this->shop->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'dealer_id' => $this->dealer->id,
            'shop_id' => $this->shop->id,
            'order_id' => $order->id,
            'type' => TransactionType::SHOP_RETURN->value,
            'reason' => ReturnReason::DEFECTIVE->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'shop_id' => $this->shop->id,
            'order_id' => $order->id,
            'type' => PaymentType::CREDIT->value,
            'amount' => 2 * 5_000,
        ]);
    }

    public function test_spoilage_disposition_does_not_change_stock_but_credits_shop(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = $this->makeDeliveredOrder($product, deliveredQty: 4);
        $item = $order->items()->first();

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), [
                'reason' => ReturnReason::EXPIRED->value,
                'items' => [[
                    'order_item_id' => $item->id,
                    'qty' => 1,
                    'disposition' => ReturnDisposition::SPOILAGE->value,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(-100_000 + 5_000, $this->shop->fresh()->balance);
    }

    public function test_cannot_return_more_than_delivered(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = $this->makeDeliveredOrder($product, deliveredQty: 2);
        $item = $order->items()->first();

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [[
                    'order_item_id' => $item->id,
                    'qty' => 5,
                    'disposition' => ReturnDisposition::RESTOCK->value,
                ]],
            ])
            ->assertSessionHasErrors(['return']);

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseMissing('transactions', [
            'type' => TransactionType::SHOP_RETURN->value,
        ]);
    }

    public function test_repeated_returns_accumulate_against_delivered_qty(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = $this->makeDeliveredOrder($product, deliveredQty: 5);
        $item = $order->items()->first();

        $payload = fn (int $qty) => [
            'reason' => ReturnReason::UNSOLD->value,
            'items' => [[
                'order_item_id' => $item->id,
                'qty' => $qty,
                'disposition' => ReturnDisposition::RESTOCK->value,
            ]],
        ];

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), $payload(2))
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), $payload(2))
            ->assertRedirect();

        // 4 of 5 already returned; trying to return 2 more should fail
        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), $payload(2))
            ->assertSessionHasErrors(['return']);

        $this->assertSame(14, (int) $product->fresh()->stock);
        $this->assertSame(2, (int) Transaction::query()->where('type', TransactionType::SHOP_RETURN->value)->count());
    }

    public function test_pending_order_cannot_be_returned(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = Order::factory()->for($this->shop)->for($this->dealer)->create([
            'status' => OrderStatus::PENDING,
            'total' => 25_000,
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 5_000,
            'qty' => 5,
            'delivered_qty' => 0,
            'pack_size' => 1,
            'unit' => $product->unit->value,
        ]);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [[
                    'order_item_id' => $item->id,
                    'qty' => 1,
                    'disposition' => ReturnDisposition::RESTOCK->value,
                ]],
            ])
            ->assertSessionHasErrors(['return']);
    }

    public function test_cannot_return_other_dealers_order(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();
        $otherProduct = Product::factory()->for($otherDealer)->create(['stock' => 10, 'price' => 5_000]);
        $order = Order::factory()->for($otherShop)->for($otherDealer)->create([
            'status' => OrderStatus::DELIVERED,
            'total' => 25_000,
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'product_id' => $otherProduct->id,
            'product_name' => $otherProduct->name,
            'price' => 5_000,
            'qty' => 5,
            'delivered_qty' => 5,
            'pack_size' => 1,
            'unit' => $otherProduct->unit->value,
        ]);

        $this->actingAs($this->owner)
            ->post(route('dealer.orders.shop-return.store', $order), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [[
                    'order_item_id' => $item->id,
                    'qty' => 1,
                    'disposition' => ReturnDisposition::RESTOCK->value,
                ]],
            ])
            ->assertNotFound();
    }

    private function makeDeliveredOrder(Product $product, float $deliveredQty): Order
    {
        $order = Order::factory()->for($this->shop)->for($this->dealer)->create([
            'status' => OrderStatus::DELIVERED,
            'total' => (int) ($deliveredQty * (float) $product->price),
            'delivered_total' => (int) ($deliveredQty * (float) $product->price),
            'number' => 1,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'qty' => $deliveredQty,
            'delivered_qty' => $deliveredQty,
            'pack_size' => 1,
            'pack_qty' => null,
            'unit' => $product->unit->value,
        ]);

        return $order->fresh(['items']);
    }
}
