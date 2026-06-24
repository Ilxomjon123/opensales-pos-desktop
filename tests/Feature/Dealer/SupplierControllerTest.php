<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_list_suppliers(): void
    {
        Supplier::factory()->count(3)->for($this->dealer)->create();

        $this->actingAs($this->owner)
            ->get(route('dealer.suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Suppliers/Index')
                ->where('suppliers.meta.total', 3));
    }

    public function test_dealer_only_sees_own_suppliers(): void
    {
        Supplier::factory()->for($this->dealer)->create(['name' => 'Mine']);
        Supplier::factory()->for(Dealer::factory()->create())->create(['name' => 'Other']);

        $this->actingAs($this->owner)
            ->get(route('dealer.suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('suppliers.meta.total', 1));
    }

    public function test_dealer_can_create_supplier(): void
    {
        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers.store'), [
                'name' => 'Coca-Cola Uz',
                'phone' => '+998901234567',
                'contact_person' => 'Ali',
                'address' => 'Toshkent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'dealer_id' => $this->dealer->id,
            'name' => 'Coca-Cola Uz',
            'phone' => '+998901234567',
        ]);
    }

    public function test_dealer_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->for($this->dealer)->create(['name' => 'Old']);

        $this->actingAs($this->owner)
            ->put(route('dealer.suppliers.update', $supplier), [
                'name' => 'New Name',
                'phone' => $supplier->phone,
            ])
            ->assertRedirect();

        $this->assertSame('New Name', $supplier->fresh()->name);
    }

    public function test_dealer_cannot_update_other_dealers_supplier(): void
    {
        $foreignSupplier = Supplier::factory()->for(Dealer::factory()->create())->create();

        $this->actingAs($this->owner)
            ->put(route('dealer.suppliers.update', $foreignSupplier), ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_destroy_marks_inactive_not_hard_delete(): void
    {
        $supplier = Supplier::factory()->for($this->dealer)->create(['is_active' => true]);

        $this->actingAs($this->owner)
            ->delete(route('dealer.suppliers.destroy', $supplier))
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => false,
        ]);
    }

    public function test_can_pay_supplier(): void
    {
        $supplier = Supplier::factory()->for($this->dealer)->create(['balance' => -500_000]);

        $this->actingAs($this->owner)
            ->post(route('dealer.suppliers-balance.payments.store'), [
                'supplier_id' => $supplier->id,
                'amount' => 500_000,
                'type' => 'credit',
                'method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertSame(0, $supplier->fresh()->balance);
        $this->assertDatabaseCount('supplier_payments', 1);
    }

    public function test_warehouse_can_create_supplier_but_not_pay(): void
    {
        $warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($warehouse)
            ->post(route('dealer.suppliers.store'), ['name' => 'Test Supplier'])
            ->assertRedirect();

        $supplier = Supplier::query()->where('name', 'Test Supplier')->first();
        $this->assertNotNull($supplier);

        $this->actingAs($warehouse)
            ->post(route('dealer.suppliers-balance.payments.store'), [
                'supplier_id' => $supplier->id,
                'amount' => 100_000,
                'type' => 'credit',
            ])
            ->assertForbidden();
    }
}
