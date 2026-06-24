<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Exceptions\Domain\BelowMinOrderAmountException;
use App\Exceptions\Domain\EmptyCartException;
use App\Exceptions\Domain\InvalidOrderTransitionException;
use App\Exceptions\Domain\OutsideDeliveryZoneException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
    }

    /**
     * Sklad chiqimi uchun yordamchi: PENDING → ASSEMBLING → DELIVERING
     * o'tishlarini to'liq oqim bilan bajaradi. Tovar tayyorlash (assemble)
     * paytida picked miqdorlar yoziladi va sklad qoldig'i kamayadi; dispatch
     * faqat status flip qiladi.
     *
     * @param  list<array{product_id: int, product_type_id?: int|null, picked_qty: int|float, picked_pack_qty?: int|null}>  $pickedItems
     */
    private function preparePickedDispatch(Order $order, array $pickedItems): Order
    {
        $owner = User::factory()->create(['dealer_id' => $order->dealer_id, 'role' => UserRole::DEALER]);
        $deliveryman = User::factory()->create(['dealer_id' => $order->dealer_id, 'role' => UserRole::DELIVERYMAN]);

        $order->update(['deliveryman_id' => $deliveryman->id]);
        $this->service->assemble($order->fresh(), $owner, $pickedItems);

        return $this->service->dispatch($order->fresh(), $owner);
    }

    public function test_creates_order_from_cart_without_touching_balance(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 50_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 2,
        ));

        $order = $this->service->createFromCart($shop, $cart);

        $this->assertSame(100_000, $order->total);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertCount(1, $order->items);
        // Saldo va sklad yetkazib berilgunga qadar tegilmaydi.
        $this->assertSame(0, $shop->fresh()->balance);
        $this->assertDatabaseMissing('payments', ['shop_id' => $shop->id]);
        $this->assertSame(20, (int) $product->fresh()->stock);

        Event::assertDispatched(OrderCreated::class);
    }

    private function singleItemCart(Product $product): Cart
    {
        return (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 1,
        ));
    }

    public function test_allows_order_when_dealer_has_no_delivery_zones(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['region' => 'Samarqand viloyati']);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 5]);

        $order = $this->service->createFromCart($shop, $this->singleItemCart($product));

        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_blocks_order_when_shop_outside_delivery_zone(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $shop = Shop::factory()->for($dealer)->create([
            'region' => 'Samarqand viloyati',
            'district' => 'Urgut tumani',
        ]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 5]);

        $this->expectException(OutsideDeliveryZoneException::class);

        $this->service->createFromCart($shop, $this->singleItemCart($product));
    }

    public function test_allows_order_within_delivery_zone(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $shop = Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
        ]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 5]);

        $order = $this->service->createFromCart($shop, $this->singleItemCart($product));

        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_whole_region_zone_covers_any_district(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => null]);

        $shop = Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Yunusobod tumani',
        ]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 5]);

        $order = $this->service->createFromCart($shop, $this->singleItemCart($product));

        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_allows_order_when_shop_region_unknown(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $shop = Shop::factory()->for($dealer)->create(['region' => null, 'district' => null]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 5]);

        $order = $this->service->createFromCart($shop, $this->singleItemCart($product));

        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_throws_on_empty_cart(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $this->expectException(EmptyCartException::class);
        $this->service->createFromCart($shop, new Cart);
    }

    public function test_throws_when_cart_total_is_below_dealer_minimum(): void
    {
        $dealer = Dealer::factory()->create(['min_order_amount' => 100_000]);
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 30_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 2,
        ));

        $this->expectException(BelowMinOrderAmountException::class);
        $this->service->createFromCart($shop, $cart);
    }

    public function test_creates_order_when_cart_total_meets_dealer_minimum(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create(['min_order_amount' => 100_000]);
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 50_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 2,
        ));

        $order = $this->service->createFromCart($shop, $cart);

        $this->assertSame(100_000, $order->total);
        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_order_is_placed_even_when_stock_is_low(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create(['stock' => 3]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 10,
        ));

        $order = $this->service->createFromCart($shop, $cart);

        $this->assertSame(OrderStatus::PENDING, $order->status);
        // Sklad yaratish paytida o'zgarmaydi (faqat dispatch da kamayadi)
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_throws_when_product_is_inactive(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 100,
            'is_active' => false,
        ]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 1,
        ));

        $this->expectException(ProductUnavailableException::class);
        $this->service->createFromCart($shop, $cart);
    }

    public function test_transition_pending_to_confirmed(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $order = Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::PENDING]);

        $updated = $this->service->transition($order, OrderStatus::ASSEMBLING);
        $this->service->transition($order->fresh(), OrderStatus::DELIVERING);

        $this->assertSame(OrderStatus::ASSEMBLING, $updated->status);
    }

    public function test_cancel_keeps_stock_and_balance_unchanged(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 50]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);

        // Cart yaratish skladga va saldoga tegmaydi.
        $this->assertSame(50, (int) $product->fresh()->stock);
        $this->assertSame(0, $shop->fresh()->balance);

        $this->service->transition($order, OrderStatus::CANCELLED);

        // Cancel ham skladga va saldoga tegmaydi (chunki hech narsa olinmagan edi).
        $this->assertSame(50, (int) $product->fresh()->stock);
        $this->assertSame(0, $shop->fresh()->balance);
        $this->assertDatabaseMissing('payments', ['shop_id' => $shop->id]);
    }

    public function test_invalid_transition_throws(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $order = Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::DELIVERED]);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->transition($order, OrderStatus::PENDING);
    }

    public function test_transition_to_delivered_is_blocked_in_generic_path(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $order = Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::ASSEMBLING]);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->transition($order, OrderStatus::DELIVERED);
    }

    public function test_deliver_full_quantity_with_full_payment(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 5],
        ]);

        $delivered = $this->service->deliver($order->fresh(), [
            ['product_id' => $product->id, 'delivered_qty' => 5],
        ], paidAmount: 50_000);

        $this->assertSame(OrderStatus::DELIVERED, $delivered->status);
        $this->assertSame(50_000, $delivered->delivered_total);
        $this->assertSame(50_000, $delivered->paid_amount);
        $this->assertSame(0, $shop->fresh()->balance);
        $this->assertSame(15, (int) $product->fresh()->stock);
    }

    public function test_deliver_partial_returns_stock_and_adjusts_balance(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 10,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        // Pick 7 (deliver to'la), 3 ta carry'da qoldirilmaydi — bu test deliver
        // miqdori qisman bo'lganda saldo to'g'ri hisoblanishini tekshiradi.
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 7],
        ]);

        $delivered = $this->service->deliver($order->fresh(), [
            ['product_id' => $product->id, 'delivered_qty' => 7],
        ], paidAmount: 50_000);

        $this->assertSame(70_000, $delivered->delivered_total);
        $this->assertSame(50_000, $delivered->paid_amount);
        // delivered 70k debit + paid 50k credit → -20k (owes 20k)
        $this->assertSame(-20_000, $shop->fresh()->balance);
        // stock: 20 - 7 picked = 13
        $this->assertSame(13, (int) $product->fresh()->stock);
    }

    public function test_deliver_more_than_ordered_takes_extra_stock_and_debits_extra(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 8],
        ]);

        $delivered = $this->service->deliver($order->fresh(), [
            ['product_id' => $product->id, 'delivered_qty' => 8],
        ], paidAmount: 80_000);

        $this->assertSame(80_000, $delivered->delivered_total);
        // delivered 80k debit + paid 80k credit → 0
        $this->assertSame(0, $shop->fresh()->balance);
        // stock: 20 - 8 picked = 12
        $this->assertSame(12, (int) $product->fresh()->stock);
    }

    public function test_deliver_with_new_product_not_in_order(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $a = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);
        $b = Product::factory()->for($dealer)->create(['price' => 5_000, 'stock' => 30]);

        $cart = (new Cart)->add(new CartItem(
            productId: $a->id,
            productName: $a->name,
            price: $a->price,
            qty: 3,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $a->id, 'picked_qty' => 3],
            ['product_id' => $b->id, 'picked_qty' => 4],
        ]);

        $delivered = $this->service->deliver($order->fresh(), [
            ['product_id' => $a->id, 'delivered_qty' => 3],
            ['product_id' => $b->id, 'delivered_qty' => 4],
        ], paidAmount: 0);

        $this->assertSame(30_000 + 20_000, $delivered->delivered_total);
        // delivered 50k (a:30k + b:20k) debit, 0 paid → owes 50k
        $this->assertSame(-50_000, $shop->fresh()->balance);
        $this->assertSame(17, (int) $a->fresh()->stock);
        $this->assertSame(26, (int) $b->fresh()->stock);
        $this->assertCount(2, $delivered->items);
    }

    public function test_transition_dispatches_status_changed_event(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $order = Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::PENDING]);

        $this->service->transition($order, OrderStatus::ASSEMBLING);
        $this->service->transition($order->fresh(), OrderStatus::DELIVERING);

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $e): bool => $e->from === OrderStatus::PENDING
                && $e->to === OrderStatus::ASSEMBLING
                && $e->order->id === $order->id,
        );
    }

    public function test_deliver_dispatches_status_changed_event(): void
    {
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 3,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 3],
        ]);

        $this->service->deliver($order->fresh(), [
            ['product_id' => $product->id, 'delivered_qty' => 3],
        ], paidAmount: 30_000);

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $e): bool => $e->to === OrderStatus::DELIVERED,
        );
    }

    public function test_deliver_with_zero_for_item_returns_all_stock(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        // Dispatch yo'q (yetkazib bo'lmaydigan zakas) — pick 0.
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 0],
        ]);

        $delivered = $this->service->deliver($order->fresh(), [
            ['product_id' => $product->id, 'delivered_qty' => 0],
        ], paidAmount: 0);

        $this->assertSame(0, $delivered->delivered_total);
        $this->assertSame(0, $shop->fresh()->balance);
        $this->assertSame(20, (int) $product->fresh()->stock);
    }

    public function test_deliver_with_discount_credits_shop_and_persists_amount(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 5],
        ]);

        // Berilgan: 50_000, chegirma: 5_000, to'landi: 45_000 → balans 0
        $delivered = $this->service->deliver(
            order: $order->fresh(),
            items: [['product_id' => $product->id, 'delivered_qty' => 5]],
            paidAmount: 45_000,
            discount: 5_000,
        );

        $this->assertSame(50_000, $delivered->delivered_total);
        $this->assertSame(5_000, $delivered->discount);
        $this->assertSame(45_000, $delivered->paid_amount);
        $this->assertSame(0, $shop->fresh()->balance);
    }

    public function test_deliver_caps_discount_at_delivered_total(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 5],
        ]);

        // Berilgan jami 50_000 dan ko'p chegirma kiritilsa, shu summagacha cheklanadi.
        $delivered = $this->service->deliver(
            order: $order->fresh(),
            items: [['product_id' => $product->id, 'delivered_qty' => 5]],
            paidAmount: 0,
            discount: 999_999,
        );

        $this->assertSame(50_000, $delivered->discount);
        $this->assertSame(0, $shop->fresh()->balance);
    }

    public function test_deliver_split_payment_creates_two_payment_rows(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 5,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 5],
        ]);

        $delivered = $this->service->deliver(
            order: $order->fresh(),
            items: [['product_id' => $product->id, 'delivered_qty' => 5]],
            paidAmount: 50_000,
            paidCard: 30_000,
            cardholderName: 'Vali Aliyev',
        );

        $this->assertSame(50_000, $delivered->paid_amount);
        $this->assertSame(0, $shop->fresh()->balance);

        $payments = $delivered->shop->payments()->where('type', 'credit')->get();
        $cash = $payments->firstWhere('method', PaymentMethod::CASH);
        $card = $payments->firstWhere('method', PaymentMethod::CARD);

        $this->assertNotNull($cash);
        $this->assertSame(20_000, $cash->amount);
        $this->assertNull($cash->cardholder_name);

        $this->assertNotNull($card);
        $this->assertSame(30_000, $card->amount);
        $this->assertSame('Vali Aliyev', $card->cardholder_name);
    }

    public function test_deliver_card_only_payment(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 3,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 3],
        ]);

        $delivered = $this->service->deliver(
            order: $order->fresh(),
            items: [['product_id' => $product->id, 'delivered_qty' => 3]],
            paidAmount: 30_000,
            paidCard: 30_000,
            cardholderName: 'Sanjar Karimov',
        );

        $this->assertSame(30_000, $delivered->paid_amount);
        $this->assertSame(0, $shop->fresh()->balance);

        $payments = $delivered->shop->payments()->where('type', 'credit')->get();
        $this->assertCount(1, $payments);
        $this->assertSame(PaymentMethod::CARD, $payments[0]->method);
        $this->assertSame('Sanjar Karimov', $payments[0]->cardholder_name);
    }

    public function test_deliver_card_payment_without_cardholder_throws(): void
    {
        Event::fake([OrderCreated::class]);

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 10_000, 'stock' => 20]);

        $cart = (new Cart)->add(new CartItem(
            productId: $product->id,
            productName: $product->name,
            price: $product->price,
            qty: 2,
        ));

        $order = $this->service->createFromCart($shop, $cart);
        $this->preparePickedDispatch($order, [
            ['product_id' => $product->id, 'picked_qty' => 2],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->deliver(
            order: $order->fresh(),
            items: [['product_id' => $product->id, 'delivered_qty' => 2]],
            paidAmount: 20_000,
            paidCard: 20_000,
            cardholderName: '',
        );
    }
}
