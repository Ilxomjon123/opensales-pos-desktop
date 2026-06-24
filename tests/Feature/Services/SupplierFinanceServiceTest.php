<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Supplier;
use App\Services\SupplierFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class SupplierFinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplierFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierFinanceService::class);
    }

    public function test_credit_increases_balance(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create(['balance' => 0]);

        $payment = $this->service->credit($supplier, 100_000, 'Eski qarz');

        $this->assertSame(PaymentType::CREDIT, $payment->type);
        $this->assertSame(100_000, $payment->amount);
        $this->assertSame(100_000, $supplier->fresh()->balance);
    }

    public function test_debit_decreases_balance(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create(['balance' => 0]);

        $this->service->debit($supplier, 50_000, 'Prixod');

        $this->assertSame(-50_000, $supplier->fresh()->balance);
    }

    public function test_multiple_operations_accumulate(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create(['balance' => 0]);

        // Prixod 200k qarz qo'shadi
        $this->service->debit($supplier, 200_000);
        // To'lov 80k qarzni kamaytiradi
        $this->service->credit($supplier, 80_000);
        // Yana prixod 30k
        $this->service->debit($supplier, 30_000);

        // -200k + 80k - 30k = -150k
        $this->assertSame(-150_000, $supplier->fresh()->balance);
        $this->assertDatabaseCount('supplier_payments', 3);
    }

    public function test_zero_amount_throws(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->credit($supplier, 0);
    }

    public function test_credit_via_card_saves_cardholder_name(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create(['balance' => 0]);

        $payment = $this->service->credit(
            supplier: $supplier,
            amount: 75_000,
            method: PaymentMethod::CARD,
            cardholderName: 'Vali Komilov',
        );

        $this->assertSame(PaymentMethod::CARD, $payment->method);
        $this->assertSame('Vali Komilov', $payment->cardholder_name);
        $this->assertSame(75_000, $supplier->fresh()->balance);
    }

    public function test_card_payment_without_cardholder_throws(): void
    {
        $dealer = Dealer::factory()->create();
        $supplier = Supplier::factory()->for($dealer)->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->credit(
            supplier: $supplier,
            amount: 10_000,
            method: PaymentMethod::CARD,
            cardholderName: '   ',
        );
    }
}
