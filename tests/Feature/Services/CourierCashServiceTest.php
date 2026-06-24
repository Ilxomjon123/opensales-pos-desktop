<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Exceptions\Domain\CourierSettlementException;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\CourierCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourierCashServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourierCashService $service;

    private Dealer $dealer;

    private User $deliveryman;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CourierCashService::class);
        $this->dealer = Dealer::factory()->create();
        $this->deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->shop = Shop::factory()->for($this->dealer)->create(['balance' => 0]);
    }

    private function cashPayment(int $amount, ?int $deliverymanId = null): Payment
    {
        return Payment::query()->create([
            'shop_id' => $this->shop->id,
            'dealer_id' => $this->dealer->id,
            'amount' => $amount,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'deliveryman_id' => $deliverymanId ?? $this->deliveryman->id,
        ]);
    }

    public function test_balance_is_zero_initially(): void
    {
        $this->assertSame(0, $this->service->balanceFor($this->deliveryman));
    }

    public function test_balance_sums_cash_credit_payments(): void
    {
        $this->cashPayment(100_000);
        $this->cashPayment(50_000);

        $this->assertSame(150_000, $this->service->balanceFor($this->deliveryman));
    }

    public function test_settle_reduces_balance(): void
    {
        $this->cashPayment(300_000);

        $this->service->settle($this->deliveryman, 100_000);

        $this->assertSame(200_000, $this->service->balanceFor($this->deliveryman));
    }

    public function test_settle_exceeding_balance_throws(): void
    {
        $this->cashPayment(50_000);

        $this->expectException(CourierSettlementException::class);
        $this->expectExceptionMessageMatches('/yetkazib beruvchi qo.+qoldiq/i');

        $this->service->settle($this->deliveryman, 100_000);
    }

    public function test_settle_negative_or_zero_amount_throws(): void
    {
        $this->expectException(CourierSettlementException::class);
        $this->service->settle($this->deliveryman, 0);
    }

    public function test_settle_on_non_deliveryman_throws(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->expectException(CourierSettlementException::class);
        $this->service->settle($owner, 1000);
    }

    public function test_balances_for_dealer_returns_per_deliveryman_totals(): void
    {
        $other = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->cashPayment(200_000);
        $this->cashPayment(70_000, $other->id);
        $this->service->settle($this->deliveryman, 50_000);

        $balances = $this->service->balancesForDealer($this->dealer->id);

        $this->assertSame(150_000, $balances[$this->deliveryman->id]);
        $this->assertSame(70_000, $balances[$other->id]);
    }

    public function test_totals_for_returns_collected_settled_balance(): void
    {
        $this->cashPayment(400_000);
        $this->service->settle($this->deliveryman, 100_000);

        $totals = $this->service->totalsFor($this->deliveryman);

        $this->assertSame([
            'collected' => 400_000,
            'settled' => 100_000,
            'balance' => 300_000,
        ], $totals);
    }
}
