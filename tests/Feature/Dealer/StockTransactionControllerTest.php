<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->supplier = Supplier::factory()->for($this->dealer)->create(['balance' => 0]);
    }

    public function test_dealer_can_record_single_product_stock_in(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 10]);

        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'note' => 'Yetkazib beruvchidan',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 25, 'unit_cost' => 5_000],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(35, (int) $product->stock);

        $this->assertDatabaseHas('transactions', [
            'dealer_id' => $this->dealer->id,
            'user_id' => $this->owner->id,
            'supplier_id' => $this->supplier->id,
            'type' => TransactionType::STOCK_IN->value,
            'note' => 'Yetkazib beruvchidan',
        ]);
        $this->assertDatabaseHas('transaction_details', [
            'product_id' => $product->id,
            'qty' => 25,
            'unit_cost' => 5_000,
            'stock_before' => 10,
            'stock_after' => 35,
        ]);

        $this->assertSame(-125_000, $this->supplier->fresh()->balance);
        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $this->supplier->id,
            'amount' => 125_000,
            'type' => 'debit',
        ]);
    }

    public function test_dealer_can_record_bulk_stock_in_for_multiple_products(): void
    {
        $a = Product::factory()->for($this->dealer)->create(['stock' => 5]);
        $b = Product::factory()->for($this->dealer)->create(['stock' => 0]);

        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'note' => null,
                'items' => [
                    ['product_id' => $a->id, 'qty' => 10, 'unit_cost' => 2_000],
                    ['product_id' => $b->id, 'qty' => 7, 'unit_cost' => 1_000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(15, (int) $a->fresh()->stock);
        $this->assertSame(7, (int) $b->fresh()->stock);
        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame(2, Transaction::query()->first()->details()->count());

        $this->assertSame(-(10 * 2_000 + 7 * 1_000), $this->supplier->fresh()->balance);
    }

    public function test_dealer_cannot_stock_in_other_dealers_product(): void
    {
        $other = Dealer::factory()->create();
        $foreignProduct = Product::factory()->for($other)->create(['stock' => 5]);

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $foreignProduct->id, 'qty' => 10, 'unit_cost' => 1_000]],
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(5, (int) $foreignProduct->fresh()->stock);
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_qty_must_be_positive(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $product->id, 'qty' => 0, 'unit_cost' => 1_000]],
            ])
            ->assertSessionHasErrors('items.0.qty');
    }

    public function test_supplier_id_required(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'items' => [['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 1_000]],
            ])
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_unit_cost_required(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $product->id, 'qty' => 5]],
            ])
            ->assertSessionHasErrors('items.0.unit_cost');
    }

    public function test_supplier_must_belong_to_dealer(): void
    {
        $other = Dealer::factory()->create();
        $foreignSupplier = Supplier::factory()->for($other)->create();
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $foreignSupplier->id,
                'items' => [['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 1_000]],
            ])
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_duplicate_product_in_payload_is_rejected(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 1_000],
                    ['product_id' => $product->id, 'qty' => 7, 'unit_cost' => 1_000],
                ],
            ])
            ->assertSessionHasErrors('items.1.product_id');
    }

    public function test_deliveryman_cannot_record_stock_in(): void
    {
        $product = Product::factory()->for($this->dealer)->create();
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 1_000]],
            ])
            ->assertRedirect(route('dealer.routes.today'));
    }

    public function test_warehouse_role_can_record_stock_in(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 0]);
        $warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($warehouse)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $product->id, 'qty' => 50, 'unit_cost' => 2_000]],
            ])
            ->assertRedirect();

        $this->assertSame(50, (int) $product->fresh()->stock);
    }

    public function test_typed_product_accepts_multiple_types_same_product(): void
    {
        $product = Product::factory()->for($this->dealer)->create([
            'has_types' => true,
            'stock' => 0,
        ]);
        $typeA = ProductType::factory()->for($product)->create(['name' => 'A', 'stock' => 0]);
        $typeB = ProductType::factory()->for($product)->create(['name' => 'B', 'stock' => 0]);

        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $product->id, 'product_type_id' => $typeA->id, 'qty' => 5, 'unit_cost' => 1_000],
                    ['product_id' => $product->id, 'product_type_id' => $typeB->id, 'qty' => 3, 'unit_cost' => 2_000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(5, (int) $typeA->fresh()->stock);
        $this->assertSame(3, (int) $typeB->fresh()->stock);
        $this->assertSame(-(5 * 1_000 + 3 * 2_000), $this->supplier->fresh()->balance);
    }

    public function test_typed_product_rejects_duplicate_same_type(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['has_types' => true]);
        $type = ProductType::factory()->for($product)->create(['name' => 'X']);

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $product->id, 'product_type_id' => $type->id, 'qty' => 5, 'unit_cost' => 1_000],
                    ['product_id' => $product->id, 'product_type_id' => $type->id, 'qty' => 7, 'unit_cost' => 1_000],
                ],
            ])
            ->assertSessionHasErrors('items.1.product_id');
    }

    public function test_partial_paid_amount_credits_supplier_balance(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 0]);

        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'paid_amount' => 30_000,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 5_000],
                ],
            ])
            ->assertRedirect();

        // Total 50k debit + 30k credit = -20k qarz qoladi
        $this->assertSame(-20_000, $this->supplier->fresh()->balance);
        $this->assertSame(2, $this->supplier->payments()->count());
    }

    public function test_full_paid_amount_zeroes_supplier_balance(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 0]);

        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'paid_amount' => 50_000,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 5_000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(0, $this->supplier->fresh()->balance);
    }

    public function test_card_paid_amount_requires_cardholder_name(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->from('/dealer/products')
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'paid_amount' => 10_000,
                'payment_method' => 'card',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 1, 'unit_cost' => 10_000],
                ],
            ])
            ->assertSessionHasErrors('cardholder_name');
    }

    public function test_dealer_can_view_history_page_scoped_to_their_dealer(): void
    {
        $other = Dealer::factory()->create();
        $myProduct = Product::factory()->for($this->dealer)->create();
        $otherProduct = Product::factory()->for($other)->create();

        // Mine
        $this->actingAs($this->owner)
            ->post(route('dealer.stock-transactions.store'), [
                'supplier_id' => $this->supplier->id,
                'items' => [['product_id' => $myProduct->id, 'qty' => 3, 'unit_cost' => 1_000]],
            ]);

        // Other dealer's transaction (raw insert to bypass scoping)
        $foreignTx = Transaction::query()->create([
            'dealer_id' => $other->id,
            'user_id' => null,
            'actor_name' => 'Other',
            'type' => TransactionType::STOCK_IN,
        ]);
        $foreignTx->details()->create([
            'product_id' => $otherProduct->id,
            'product_name' => $otherProduct->name,
            'qty' => 99,
            'stock_before' => 0,
            'stock_after' => 99,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.stock-transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Products/StockHistory')
                ->where('transactions.meta.total', 1));
    }
}
