<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class FinanceControllerTest extends TestCase
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
        $this->shop = Shop::factory()->for($this->dealer)->create(['balance' => 0]);
    }

    public function test_dealer_can_view_finance(): void
    {
        $this->actingAs($this->user)
            ->get(route('dealer.finance.index'))
            ->assertOk();
    }

    public function test_dealer_can_add_credit_payment(): void
    {
        $this->actingAs($this->user)
            ->post(route('dealer.finance.payments.store'), [
                'shop_id' => $this->shop->id,
                'amount' => 100_000,
                'type' => 'credit',
                'note' => 'Naqd to\'lov',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'shop_id' => $this->shop->id,
            'amount' => 100_000,
            'type' => 'credit',
        ]);

        $this->assertSame(100_000, $this->shop->fresh()->balance);
    }

    public function test_dealer_can_add_debit_payment(): void
    {
        $this->actingAs($this->user)
            ->post(route('dealer.finance.payments.store'), [
                'shop_id' => $this->shop->id,
                'amount' => 50_000,
                'type' => 'debit',
            ])
            ->assertRedirect();

        $this->assertSame(-50_000, $this->shop->fresh()->balance);
    }

    public function test_dealer_cannot_pay_to_others_shop(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();

        $this->actingAs($this->user)
            ->post(route('dealer.finance.payments.store'), [
                'shop_id' => $otherShop->id,
                'amount' => 100_000,
                'type' => 'credit',
            ])
            ->assertSessionHasErrors('shop_id');
    }

    public function test_payment_validation_requires_positive_amount(): void
    {
        $this->actingAs($this->user)
            ->post(route('dealer.finance.payments.store'), [
                'shop_id' => $this->shop->id,
                'amount' => 0,
                'type' => 'credit',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_finance_payload_exposes_order_id_for_order_link(): void
    {
        $order = Order::factory()->for($this->dealer)->for($this->shop)->create();

        Payment::factory()->for($this->shop)->create([
            'dealer_id' => $this->dealer->id,
            'order_id' => $order->id,
            'note' => "Buyurtma #{$order->displayNumber()}",
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.finance.index'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('payments.data.0.order_id', $order->id)
            );
    }

    public function test_guest_cannot_access_finance(): void
    {
        $this->get(route('dealer.finance.index'))->assertRedirect(route('login'));
    }
}
