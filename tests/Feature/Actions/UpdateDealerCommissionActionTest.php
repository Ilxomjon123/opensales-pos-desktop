<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\UpdateDealerCommissionAction;
use App\Enums\CommissionType;
use App\Models\Dealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateDealerCommissionActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateDealerCommissionAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(UpdateDealerCommissionAction::class);
    }

    public function test_dealer_creation_seeds_initial_percentage_period(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertCount(1, $dealer->commissionPeriods()->get());
        $period = $dealer->commissionPeriods()->first();
        $this->assertSame(CommissionType::TURNOVER_PERCENTAGE, $period->commission_type);
        $this->assertNull($period->ends_at);
        $this->assertNull($period->fixed_commission_amount);
    }

    public function test_switching_from_percentage_to_fixed_closes_old_period_and_opens_new(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);

        $this->action->execute(
            dealer: $dealer,
            type: CommissionType::FIXED_PER_SHOP,
            fixedAmount: 5_000,
        );

        $periods = $dealer->commissionPeriods()->orderBy('starts_at')->get();
        $this->assertCount(2, $periods);
        $this->assertNotNull($periods[0]->ends_at);
        $this->assertSame(CommissionType::TURNOVER_PERCENTAGE, $periods[0]->commission_type);
        $this->assertNull($periods[1]->ends_at);
        $this->assertSame(CommissionType::FIXED_PER_SHOP, $periods[1]->commission_type);
        $this->assertSame(5_000, $periods[1]->fixed_commission_amount);

        $fresh = $dealer->fresh();
        $this->assertSame(CommissionType::FIXED_PER_SHOP, $fresh->commission_type);
        $this->assertSame(5_000, $fresh->fixed_commission_amount);
    }

    public function test_switching_back_to_percentage_clears_fixed_amount(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);
        $this->action->execute($dealer, CommissionType::FIXED_PER_SHOP, fixedAmount: 5_000);
        $this->action->execute($dealer->fresh(), CommissionType::TURNOVER_PERCENTAGE, percentageRate: 10.5);

        $fresh = $dealer->fresh();
        $this->assertSame(CommissionType::TURNOVER_PERCENTAGE, $fresh->commission_type);
        $this->assertNull($fresh->fixed_commission_amount);
        $this->assertSame('10.50', (string) $fresh->platform_fee_rate);

        $this->assertCount(3, $fresh->commissionPeriods()->get());
        $this->assertNull($fresh->currentCommissionPeriod->ends_at);
        $this->assertSame(CommissionType::TURNOVER_PERCENTAGE, $fresh->currentCommissionPeriod->commission_type);
    }

    public function test_switching_to_fixed_per_order_persists_amount_on_dealer_and_period(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);

        $this->action->execute(
            dealer: $dealer,
            type: CommissionType::FIXED_PER_ORDER,
            fixedAmount: 12_000,
        );

        $fresh = $dealer->fresh();
        $this->assertSame(CommissionType::FIXED_PER_ORDER, $fresh->commission_type);
        $this->assertSame(12_000, $fresh->fixed_commission_amount);

        $current = $fresh->currentCommissionPeriod;
        $this->assertNotNull($current);
        $this->assertSame(CommissionType::FIXED_PER_ORDER, $current->commission_type);
        $this->assertSame(12_000, $current->fixed_commission_amount);
        $this->assertNull($current->ends_at);
    }

    public function test_switching_to_fixed_per_deliveryman_persists_amount_on_dealer_and_period(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);

        $this->action->execute(
            dealer: $dealer,
            type: CommissionType::FIXED_PER_DELIVERYMAN,
            fixedAmount: 15_000,
        );

        $fresh = $dealer->fresh();
        $this->assertSame(CommissionType::FIXED_PER_DELIVERYMAN, $fresh->commission_type);
        $this->assertSame(15_000, $fresh->fixed_commission_amount);

        $current = $fresh->currentCommissionPeriod;
        $this->assertNotNull($current);
        $this->assertSame(CommissionType::FIXED_PER_DELIVERYMAN, $current->commission_type);
        $this->assertSame(15_000, $current->fixed_commission_amount);
        $this->assertNull($current->ends_at);
    }
}
