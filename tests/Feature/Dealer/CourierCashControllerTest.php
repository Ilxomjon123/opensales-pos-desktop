<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\CourierSettlement;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourierCashControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $owner;

    private User $warehouse;

    private User $deliveryman;

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
        $this->warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->shop = Shop::factory()->for($this->dealer)->create(['balance' => 0]);
    }

    public function test_owner_can_view_index(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.courier-cash.index'))
            ->assertOk();
    }

    public function test_warehouse_cannot_view_index(): void
    {
        $this->actingAs($this->warehouse)
            ->get(route('dealer.courier-cash.index'))
            ->assertForbidden();
    }

    public function test_warehouse_cannot_view_show(): void
    {
        $this->actingAs($this->warehouse)
            ->get(route('dealer.courier-cash.show', $this->deliveryman))
            ->assertForbidden();
    }

    public function test_deliveryman_index_redirects_to_own_show(): void
    {
        $this->actingAs($this->deliveryman)
            ->get(route('dealer.courier-cash.index'))
            ->assertRedirect(route('dealer.courier-cash.show', $this->deliveryman));
    }

    public function test_deliveryman_can_view_own_show(): void
    {
        $this->actingAs($this->deliveryman)
            ->get(route('dealer.courier-cash.show', $this->deliveryman))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_settle', false)
            );
    }

    public function test_deliveryman_cannot_view_other_deliveryman_show(): void
    {
        $other = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($this->deliveryman)
            ->get(route('dealer.courier-cash.show', $other))
            ->assertForbidden();
    }

    public function test_deliveryman_cannot_settle_own_balance(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->deliveryman)
            ->post(route('dealer.courier-cash.settle', $this->deliveryman), [
                'amount' => 50_000,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('courier_settlements', 0);
    }

    public function test_owner_show_returns_can_settle_true(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.courier-cash.show', $this->deliveryman))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_settle', true)
            );
    }

    public function test_index_shows_balance_for_deliveryman_with_cash_collected(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 250_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.courier-cash.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.total_balance', 250_000)
                ->where('summary.with_balance', 1)
            );
    }

    public function test_settle_creates_settlement_and_reduces_balance(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 500_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('dealer.courier-cash.settle', $this->deliveryman), [
                'amount' => 300_000,
                'note' => 'Test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courier_settlements', [
            'deliveryman_id' => $this->deliveryman->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 300_000,
            'settled_by_user_id' => $this->owner->id,
            'note' => 'Test',
        ]);
    }

    public function test_warehouse_cannot_settle(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->warehouse)
            ->post(route('dealer.courier-cash.settle', $this->deliveryman), [
                'amount' => 100_000,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('courier_settlements', 0);
    }

    public function test_settle_amount_exceeding_balance_fails(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('dealer.courier-cash.settle', $this->deliveryman), [
                'amount' => 200_000,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('courier_settlements', 0);
    }

    public function test_partial_settlements_accumulate(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 500_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->owner);

        $this->post(route('dealer.courier-cash.settle', $this->deliveryman), ['amount' => 200_000])->assertRedirect();
        $this->post(route('dealer.courier-cash.settle', $this->deliveryman), ['amount' => 150_000])->assertRedirect();

        $this->assertSame(2, CourierSettlement::query()->count());
        $this->assertSame(350_000, (int) CourierSettlement::query()->sum('amount'));
    }

    public function test_cannot_settle_against_other_dealers_deliveryman(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherDeliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $otherDealer->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('dealer.courier-cash.settle', $otherDeliveryman), [
                'amount' => 10_000,
            ])
            ->assertNotFound();
    }

    public function test_card_payment_does_not_appear_on_courier_balance(): void
    {
        Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CARD,
            'cardholder_name' => 'Test',
            'deliveryman_id' => $this->deliveryman->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.courier-cash.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.total_balance', 0)
            );
    }
}
