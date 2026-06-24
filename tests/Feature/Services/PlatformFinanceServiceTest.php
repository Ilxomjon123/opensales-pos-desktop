<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Actions\UpdateDealerCommissionAction;
use App\Enums\CommissionType;
use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\PlatformPayment;
use App\Models\Shop;
use App\Models\User;
use App\Services\PlatformFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class PlatformFinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformFinanceService::class);
    }

    public function test_snapshot_for_dealer_without_orders_is_zero(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);

        $snap = $this->service->snapshot($dealer);

        $this->assertSame(0, $snap['turnover']);
        $this->assertSame(0, $snap['fee_owed']);
        $this->assertSame(0, $snap['total_paid']);
        $this->assertSame(0, $snap['balance']);
        $this->assertSame(5.0, $snap['fee_rate']);
    }

    public function test_snapshot_counts_only_delivered_orders_in_turnover(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 10]);

        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'delivered_total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 500_000, 'delivered_total' => 500_000, 'platform_fee_rate' => 10,
        ]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::PENDING, 'total' => 999_999, 'delivered_total' => 0, 'platform_fee_rate' => 10,
        ]);

        $snap = $this->service->snapshot($dealer);

        $this->assertSame(1_500_000, $snap['turnover']);
        $this->assertSame(150_000, $snap['fee_owed']);
    }

    public function test_balance_is_negative_when_dealer_owes(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 2_000_000, 'platform_fee_rate' => 5,
        ]);
        PlatformPayment::query()->create(['dealer_id' => $dealer->id, 'amount' => 30_000]);

        $snap = $this->service->snapshot($dealer);

        $this->assertSame(100_000, $snap['fee_owed']);
        $this->assertSame(30_000, $snap['total_paid']);
        $this->assertSame(-70_000, $snap['balance']);
    }

    public function test_balance_is_positive_when_dealer_overpaid(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 5,
        ]);
        PlatformPayment::query()->create(['dealer_id' => $dealer->id, 'amount' => 200_000]);

        $snap = $this->service->snapshot($dealer);

        $this->assertSame(150_000, $snap['balance']);
    }

    public function test_discount_reduces_balance_like_cash_payment(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 10]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);

        // 100k owed; 30k cash + 20k discount = 50k settled
        PlatformPayment::query()->create([
            'dealer_id' => $dealer->id,
            'amount' => 30_000,
            'discount' => 20_000,
        ]);

        $snap = $this->service->snapshot($dealer);

        $this->assertSame(100_000, $snap['fee_owed']);
        $this->assertSame(30_000, $snap['total_paid']);
        $this->assertSame(20_000, $snap['total_discount']);
        $this->assertSame(-50_000, $snap['balance']);
    }

    public function test_totals_include_discount(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 10]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);
        PlatformPayment::query()->create([
            'dealer_id' => $dealer->id,
            'amount' => 40_000,
            'discount' => 10_000,
        ]);

        $totals = $this->service->totals();

        $this->assertSame(40_000, $totals['total_paid']);
        $this->assertSame(10_000, $totals['total_discount']);
        $this->assertSame(-50_000, $totals['balance']);
    }

    public function test_totals_aggregate_across_dealers_with_per_dealer_rate(): void
    {
        $d1 = Dealer::factory()->create(['platform_fee_rate' => 10]);
        $d2 = Dealer::factory()->create(['platform_fee_rate' => 3]);

        Order::factory()->for($d1)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'delivered_total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);
        Order::factory()->for($d2)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 2_000_000, 'delivered_total' => 2_000_000, 'platform_fee_rate' => 3,
        ]);

        PlatformPayment::query()->create(['dealer_id' => $d1->id, 'amount' => 50_000]);
        PlatformPayment::query()->create(['dealer_id' => $d2->id, 'amount' => 70_000]);

        $totals = $this->service->totals();

        $this->assertSame(3_000_000, $totals['turnover']);
        // d1: 1M × 10% = 100k; d2: 2M × 3% = 60k → 160k
        $this->assertSame(160_000, $totals['fee_owed']);
        $this->assertSame(120_000, $totals['total_paid']);
        $this->assertSame(-40_000, $totals['balance']);
    }

    public function test_rate_change_does_not_affect_old_orders(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5]);

        // Eski buyurtma — yaratilganda rate 5% edi (snapshot)
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 5,
        ]);

        // Admin keyin komissiyani 20% ga ko'taradi
        $dealer->update(['platform_fee_rate' => 20]);

        // Yangi buyurtma — endi 20% bilan
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 20,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // Eski: 1M × 5% = 50k; Yangi: 1M × 20% = 200k → jami 250k
        // Agar snapshot ishlamasa: 2M × 20% = 400k bo'lar edi
        $this->assertSame(250_000, $snap['fee_owed']);
        $this->assertSame(20.0, $snap['fee_rate']);
    }

    public function test_fixed_per_shop_charges_distinct_shops_per_month(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_SHOP,
            fixedAmount: 5_000,
        );

        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();
        $shopC = Shop::factory()->for($dealer)->create();

        // Bir oyda shopA va shopB buyurtma berdi (shopA dan ikkita — distinct hisoblanadi)
        Order::factory()->for($dealer)->for($shopA)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 100_000, 'platform_fee_rate' => null,
        ]);
        Order::factory()->for($dealer)->for($shopA)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 200_000, 'platform_fee_rate' => null,
        ]);
        Order::factory()->for($dealer)->for($shopB)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 50_000, 'platform_fee_rate' => null,
        ]);
        // shopC delivered yo'q (faqat pending) — hisoblanmaydi
        Order::factory()->for($dealer)->for($shopC)->create([
            'status' => OrderStatus::PENDING, 'total' => 30_000, 'platform_fee_rate' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // 2 unique do'kon (A, B) × 5000 = 10000
        $this->assertSame(10_000, $snap['fee_owed']);
        $this->assertSame(CommissionType::FIXED_PER_SHOP->value, $snap['commission_type']);
        $this->assertSame(5_000, $snap['fixed_commission_amount']);
    }

    public function test_period_switch_uses_old_type_for_old_orders(): void
    {
        // Diller 3 oy oldin yaratilgan — boshlang'ich PERCENTAGE period ham shu vaqtdan boshlanadi
        Carbon::setTestNow(CarbonImmutable::now()->subMonthsNoOverflow(3));
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 10]);

        // 2 oy oldin DELIVERED buyurtma — PERCENTAGE period ichida
        Carbon::setTestNow(CarbonImmutable::now()->addMonthNoOverflow());
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);

        // Bugun FIXED ga o'tkaziladi
        Carbon::setTestNow(CarbonImmutable::now()->addMonthsNoOverflow(2));
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_SHOP,
            fixedAmount: 7_000,
        );

        // Yangi tipda — 1 ta unique do'kon shu oyda
        $shop = Shop::factory()->for($dealer)->create();
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 500_000, 'platform_fee_rate' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // Eski period: 1M × 10% = 100k (PERCENTAGE)
        // Yangi period: 1 do'kon × 7000 = 7000 (FIXED)
        // Jami: 107000
        $this->assertSame(107_000, $snap['fee_owed']);

        Carbon::setTestNow();
    }

    public function test_fixed_per_shop_with_no_delivered_orders_charges_zero(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_SHOP,
            fixedAmount: 5_000,
        );

        // Shop bor lekin delivered buyurtma yo'q
        Shop::factory()->for($dealer)->create();

        $snap = $this->service->snapshot($dealer->fresh());

        $this->assertSame(0, $snap['fee_owed']);
    }

    public function test_fixed_per_order_charges_per_delivered_order_regardless_of_shop(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_ORDER,
            fixedAmount: 8_000,
        );

        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();

        // 3 ta DELIVERED — bittasi shopB, ikkitasi shopA (do'kon distinct emas)
        Order::factory()->for($dealer)->for($shopA)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 100_000, 'platform_fee_rate' => null,
        ]);
        Order::factory()->for($dealer)->for($shopA)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 200_000, 'platform_fee_rate' => null,
        ]);
        Order::factory()->for($dealer)->for($shopB)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 50_000, 'platform_fee_rate' => null,
        ]);
        // Pending va cancelled hisoblanmaydi
        Order::factory()->for($dealer)->for($shopA)->create([
            'status' => OrderStatus::PENDING, 'total' => 30_000, 'platform_fee_rate' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // 3 ta DELIVERED × 8000 = 24000
        $this->assertSame(24_000, $snap['fee_owed']);
        $this->assertSame(CommissionType::FIXED_PER_ORDER->value, $snap['commission_type']);
        $this->assertSame(8_000, $snap['fixed_commission_amount']);
    }

    public function test_fixed_per_order_with_no_delivered_orders_charges_zero(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_ORDER,
            fixedAmount: 8_000,
        );

        $shop = Shop::factory()->for($dealer)->create();
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::PENDING, 'total' => 100_000, 'platform_fee_rate' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        $this->assertSame(0, $snap['fee_owed']);
    }

    public function test_fixed_per_deliveryman_charges_distinct_deliverymen_per_month(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_DELIVERYMAN,
            fixedAmount: 12_000,
        );

        $shop = Shop::factory()->for($dealer)->create();
        $courierA = User::factory()->deliveryman($dealer->id)->create();
        $courierB = User::factory()->deliveryman($dealer->id)->create();
        $courierC = User::factory()->deliveryman($dealer->id)->create();

        // courierA — 2 ta DELIVERED (distinct hisoblanadi)
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 100_000, 'platform_fee_rate' => null,
            'deliveryman_id' => $courierA->id,
        ]);
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 200_000, 'platform_fee_rate' => null,
            'deliveryman_id' => $courierA->id,
        ]);
        // courierB — 1 ta DELIVERED
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 50_000, 'platform_fee_rate' => null,
            'deliveryman_id' => $courierB->id,
        ]);
        // courierC — faqat PENDING, hisoblanmaydi
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::PENDING, 'total' => 30_000, 'platform_fee_rate' => null,
            'deliveryman_id' => $courierC->id,
        ]);
        // deliveryman_id=null — DISTINCT da hisoblanmaydi
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 70_000, 'platform_fee_rate' => null,
            'deliveryman_id' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // 2 unique deliveryman (A, B) × 12 000 = 24 000
        $this->assertSame(24_000, $snap['fee_owed']);
        $this->assertSame(CommissionType::FIXED_PER_DELIVERYMAN->value, $snap['commission_type']);
        $this->assertSame(12_000, $snap['fixed_commission_amount']);
    }

    public function test_fixed_per_deliveryman_with_no_delivered_orders_charges_zero(): void
    {
        $dealer = Dealer::factory()->create();
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_DELIVERYMAN,
            fixedAmount: 12_000,
        );

        $shop = Shop::factory()->for($dealer)->create();
        $courier = User::factory()->deliveryman($dealer->id)->create();
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::PENDING, 'total' => 100_000, 'platform_fee_rate' => null,
            'deliveryman_id' => $courier->id,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        $this->assertSame(0, $snap['fee_owed']);
    }

    public function test_period_switch_to_fixed_per_order_uses_old_type_for_old_orders(): void
    {
        Carbon::setTestNow(CarbonImmutable::now()->subMonthsNoOverflow(3));
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 10]);

        // Eski period (PERCENTAGE) ichida 2 ta delivered buyurtma
        Carbon::setTestNow(CarbonImmutable::now()->addMonthNoOverflow());
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 1_000_000, 'platform_fee_rate' => 10,
        ]);
        Order::factory()->for($dealer)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 500_000, 'platform_fee_rate' => 10,
        ]);

        // Bugun FIXED_PER_ORDER ga o'tkaziladi
        Carbon::setTestNow(CarbonImmutable::now()->addMonthsNoOverflow(2));
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_PER_ORDER,
            fixedAmount: 7_000,
        );

        // Yangi period: 2 ta DELIVERED
        $shop = Shop::factory()->for($dealer)->create();
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 300_000, 'platform_fee_rate' => null,
        ]);
        Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED, 'total' => 400_000, 'platform_fee_rate' => null,
        ]);

        $snap = $this->service->snapshot($dealer->fresh());

        // Eski: 1.5M × 10% = 150 000
        // Yangi: 2 × 7 000 = 14 000
        $this->assertSame(164_000, $snap['fee_owed']);

        Carbon::setTestNow();
    }

    public function test_fixed_monthly_charges_amount_per_month_regardless_of_activity(): void
    {
        // Diller 2 oy oldin yaratilgan
        Carbon::setTestNow(CarbonImmutable::now()->subMonthsNoOverflow(2)->startOfMonth());
        $dealer = Dealer::factory()->create();

        // Bugun (3 oy ichida — joriy oy + 2 oy oldingi) FIXED_MONTHLY ga o'tkaziladi
        Carbon::setTestNow(CarbonImmutable::now()->addMonthsNoOverflow(2));
        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_MONTHLY,
            fixedAmount: 500_000,
        );

        // Buyurtma yo'q — fee faqat oylik summa
        $snap = $this->service->snapshot($dealer->fresh());

        // Joriy faol period (boshlanish — bugun) faqat 1 oyni qamraydi
        $this->assertSame(500_000, $snap['fee_owed']);
        $this->assertSame(CommissionType::FIXED_MONTHLY->value, $snap['commission_type']);
        $this->assertSame(500_000, $snap['fixed_commission_amount']);

        Carbon::setTestNow();
    }

    public function test_fixed_monthly_multiplies_by_number_of_months_in_period(): void
    {
        // 3 oy oldin diller yaratiladi va darhol FIXED_MONTHLY ga o'tkaziladi
        Carbon::setTestNow(CarbonImmutable::now()->subMonthsNoOverflow(3)->startOfMonth());
        $dealer = Dealer::factory()->create();

        app(UpdateDealerCommissionAction::class)->execute(
            $dealer,
            CommissionType::FIXED_MONTHLY,
            fixedAmount: 500_000,
        );

        // Bugun joriy oygacha 4 oy o'tdi (3 oy oldin boshlangan + bugungi oy) → 4 × 500k = 2 000 000
        Carbon::setTestNow(CarbonImmutable::now()->addMonthsNoOverflow(3));

        $snap = $this->service->snapshot($dealer->fresh());

        $this->assertSame(2_000_000, $snap['fee_owed']);

        Carbon::setTestNow();
    }
}
