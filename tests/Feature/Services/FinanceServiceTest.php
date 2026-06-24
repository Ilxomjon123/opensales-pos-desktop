<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Shop;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class FinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinanceService::class);
    }

    public function test_credit_increases_balance(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $payment = $this->service->credit($shop, 100_000, 'To\'lov');

        $this->assertSame(PaymentType::CREDIT, $payment->type);
        $this->assertSame(100_000, $payment->amount);
        $this->assertSame(100_000, $shop->fresh()->balance);
    }

    public function test_debit_decreases_balance(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $this->service->debit($shop, 50_000);

        $this->assertSame(-50_000, $shop->fresh()->balance);
    }

    public function test_multiple_operations_accumulate(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $this->service->credit($shop, 200_000);
        $this->service->debit($shop, 80_000);
        $this->service->credit($shop, 30_000);

        $this->assertSame(150_000, $shop->fresh()->balance);
        $this->assertDatabaseCount('payments', 3);
    }

    public function test_zero_amount_throws(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->credit($shop, 0);
    }

    public function test_credit_defaults_to_cash_method(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $payment = $this->service->credit($shop, 50_000);

        $this->assertSame(PaymentMethod::CASH, $payment->method);
        $this->assertNull($payment->cardholder_name);
    }

    public function test_credit_via_card_saves_cardholder_name(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $payment = $this->service->credit(
            shop: $shop,
            amount: 75_000,
            method: PaymentMethod::CARD,
            cardholderName: 'Ali Valiyev',
        );

        $this->assertSame(PaymentMethod::CARD, $payment->method);
        $this->assertSame('Ali Valiyev', $payment->cardholder_name);
        $this->assertSame(75_000, $shop->fresh()->balance);
    }

    public function test_card_payment_without_cardholder_throws(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->credit(
            shop: $shop,
            amount: 10_000,
            method: PaymentMethod::CARD,
            cardholderName: '   ',
        );
    }
}
