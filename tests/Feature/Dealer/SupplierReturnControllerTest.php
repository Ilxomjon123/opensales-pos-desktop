<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\PaymentType;
use App\Enums\ReturnReason;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierReturnControllerTest extends TestCase
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
        $this->supplier = Supplier::factory()->for($this->dealer)->create(['balance' => -100_000]);
    }

    public function test_records_supplier_return_decreases_stock_and_credits_supplier(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 50]);

        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers.return.store', $this->supplier), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'note' => 'sifati past',
                'items' => [[
                    'product_id' => $product->id,
                    'qty' => 10,
                    'unit_cost' => 4_000,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(40, (int) $product->fresh()->stock);
        $this->assertSame(-100_000 + 10 * 4_000, $this->supplier->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'dealer_id' => $this->dealer->id,
            'supplier_id' => $this->supplier->id,
            'type' => TransactionType::SUPPLIER_RETURN->value,
            'reason' => ReturnReason::DEFECTIVE->value,
        ]);

        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $this->supplier->id,
            'type' => PaymentType::CREDIT->value,
            'amount' => 10 * 4_000,
        ]);
    }

    public function test_cannot_return_more_than_available_stock(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 5]);

        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers.return.store', $this->supplier), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [[
                    'product_id' => $product->id,
                    'qty' => 10,
                    'unit_cost' => 4_000,
                ]],
            ])
            ->assertSessionHasErrors(['return']);

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(-100_000, $this->supplier->fresh()->balance);
    }

    public function test_cannot_target_other_dealers_supplier(): void
    {
        $other = Dealer::factory()->create();
        $otherSupplier = Supplier::factory()->for($other)->create();
        $product = Product::factory()->for($this->dealer)->create(['stock' => 50]);

        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers.return.store', $otherSupplier), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [[
                    'product_id' => $product->id,
                    'qty' => 1,
                    'unit_cost' => 1_000,
                ]],
            ])
            ->assertNotFound();
    }

    public function test_multiple_lines_rejected_when_duplicated_product(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['stock' => 50]);

        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers.return.store', $this->supplier), [
                'reason' => ReturnReason::DEFECTIVE->value,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 1, 'unit_cost' => 1_000],
                    ['product_id' => $product->id, 'qty' => 2, 'unit_cost' => 1_000],
                ],
            ])
            ->assertSessionHasErrors(['return']);

        $this->assertSame(50, (int) $product->fresh()->stock);
    }
}
