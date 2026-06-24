<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Actions\RecordStockTransactionAction;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductTypesFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TG = 555;

    public function test_cart_keeps_two_types_of_same_product_separately(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'has_types' => true,
            'price' => 0,
            'stock' => 0,
        ]);

        $red = ProductType::factory()->for($product)->create([
            'name' => 'Qizil', 'price' => 1_000, 'stock' => 50,
        ]);
        $blue = ProductType::factory()->for($product)->create([
            'name' => "Ko'k", 'price' => 1_500, 'stock' => 50,
        ]);

        $shop = Shop::factory()->for($dealer)->create();
        $cart = app(CartService::class);

        $cart->addItem(self::TG, $shop->id, $product, 2, type: $red);
        $cart->addItem(self::TG, $shop->id, $product, 3, type: $blue);

        $stored = $cart->get(self::TG, $shop->id);

        $this->assertCount(2, $stored);
        $this->assertSame(2_000 + 4_500, $stored->total());

        $redItem = $stored->get($product->id, $red->id);
        $blueItem = $stored->get($product->id, $blue->id);

        $this->assertNotNull($redItem);
        $this->assertSame('Qizil', $redItem->productTypeName);
        $this->assertSame(2, (int) $redItem->qty);

        $this->assertNotNull($blueItem);
        $this->assertSame("Ko'k", $blueItem->productTypeName);
        $this->assertSame(3, (int) $blueItem->qty);
    }

    public function test_order_creation_takes_type_snapshot_without_touching_stock(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'has_types' => true,
            'price' => 0,
            'stock' => 0,
        ]);
        $type = ProductType::factory()->for($product)->create([
            'name' => 'Type-A', 'price' => 5_000, 'stock' => 10,
        ]);

        $shop = Shop::factory()->for($dealer)->create();
        $cart = app(CartService::class);
        $cart->addItem(self::TG, $shop->id, $product, 4, type: $type);

        $order = app(OrderService::class)->createFromCart(
            shop: $shop,
            cart: $cart->get(self::TG, $shop->id),
            cartOwnerTelegramId: self::TG,
        );

        $this->assertSame(20_000, $order->total);
        $this->assertCount(1, $order->items);

        $item = $order->items->first();
        $this->assertSame($type->id, $item->product_type_id);
        $this->assertSame('Type-A', $item->product_type_name);

        // Yangi arxitektura: sklad faqat assemble (tayyorlash) paytida kamayadi, cart paytida emas.
        $this->assertSame(10, (int) $type->fresh()->stock);
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_record_stock_transaction_with_type_updates_type_stock(): void
    {
        $dealer = Dealer::factory()->create();
        $actor = User::factory()->create([
            'dealer_id' => $dealer->id,
            'role' => UserRole::DEALER,
        ]);
        $supplier = Supplier::factory()->for($dealer)->create();
        $product = Product::factory()->for($dealer)->create([
            'has_types' => true,
            'price' => 0,
            'stock' => 0,
        ]);
        $type = ProductType::factory()->for($product)->create([
            'name' => 'Tip-1',
            'stock' => 5,
        ]);

        $action = app(RecordStockTransactionAction::class);
        $tx = $action->execute(
            actor: $actor,
            dealerId: $dealer->id,
            type: TransactionType::STOCK_IN,
            lines: [['product_id' => $product->id, 'product_type_id' => $type->id, 'qty' => 7, 'unit_cost' => 1_000]],
            supplierId: $supplier->id,
        );

        $this->assertSame(12, (int) $type->fresh()->stock);
        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertCount(1, $tx->details);

        $detail = $tx->details->first();
        $this->assertSame($type->id, $detail->product_type_id);
        $this->assertSame('Tip-1', $detail->product_type_name);
        $this->assertSame(5, (int) $detail->stock_before);
        $this->assertSame(12, (int) $detail->stock_after);
    }

    public function test_record_stock_transaction_rejects_typed_product_without_type(): void
    {
        $dealer = Dealer::factory()->create();
        $actor = User::factory()->create([
            'dealer_id' => $dealer->id,
            'role' => UserRole::DEALER,
        ]);
        $product = Product::factory()->for($dealer)->create([
            'has_types' => true,
            'stock' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(RecordStockTransactionAction::class)->execute(
            actor: $actor,
            dealerId: $dealer->id,
            type: TransactionType::STOCK_IN,
            lines: [['product_id' => $product->id, 'qty' => 5]],
        );
    }
}
