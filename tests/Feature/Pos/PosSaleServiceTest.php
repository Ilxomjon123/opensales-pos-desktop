<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleChannel;
use App\Enums\UserRole;
use App\Exceptions\Domain\PosSaleException;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\PosSaleService;
use App\Services\PosShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PosSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private PosShiftService $shifts;

    private PosSaleService $sales;

    private Dealer $dealer;

    private User $cashier;

    private Shop $walkIn;

    private Shop $individual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shifts = app(PosShiftService::class);
        $this->sales = app(PosSaleService::class);

        $this->dealer = Dealer::factory()->create();
        $this->cashier = User::factory()->create([
            'role' => UserRole::CASHIER,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->walkIn = Shop::factory()->for($this->dealer)->walkIn()->create();
        $this->individual = Shop::factory()->for($this->dealer)->individual()->create([
            'name' => 'Sanjar',
            'balance' => 0,
        ]);
    }

    public function test_creates_paid_sale_decrements_stock_and_sets_status(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create([
            'price' => 50_000,
            'stock' => 10,
        ]);

        $sale = $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 2]],
            paidCash: 100_000,
            paidCard: 0,
        );

        $this->assertSame(SaleChannel::POS, $sale->sale_channel);
        $this->assertSame(OrderStatus::RECEIVED, $sale->status);
        $this->assertSame(100_000, $sale->total);
        $this->assertSame(0, $sale->debt_amount);
        $this->assertSame(OrderPaymentStatus::PAID, $sale->payment_status);
        $this->assertSame(8.0, (float) $product->fresh()->stock);
        $this->assertNotNull($sale->receipt_number);

        // Walk-in mijoz balansi har doim 0 — naqd to'lov saldoga ta'sir qilmaydi
        $this->assertSame(0, (int) $this->walkIn->fresh()->balance);
    }

    public function test_walk_in_cannot_be_sold_on_credit(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 50_000, 'stock' => 5]);

        $this->expectException(PosSaleException::class);
        $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 2]],
            paidCash: 0,
            paidCard: 0,
        );
    }

    public function test_individual_customer_debt_recorded_on_balance(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 50_000, 'stock' => 5]);

        $sale = $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->individual,
            items: [['product_id' => $product->id, 'qty' => 2]],
            paidCash: 30_000,
            paidCard: 0,
        );

        $this->assertSame(70_000, $sale->debt_amount);
        $this->assertSame(OrderPaymentStatus::PARTIAL, $sale->payment_status);
        $this->assertSame(-70_000, (int) $this->individual->fresh()->balance);
    }

    public function test_walk_in_overpayment_allowed_and_credits_walk_in_balance(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 5]);

        $sale = $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 50_000,
            paidCard: 0,
        );

        $this->assertSame(10_000, $sale->total);
        $this->assertSame(50_000, $sale->paid_amount);
        $this->assertSame(0, $sale->debt_amount);
        $this->assertSame(OrderPaymentStatus::PAID, $sale->payment_status);
        $this->assertSame(40_000, (int) $this->walkIn->fresh()->balance);
    }

    public function test_individual_overpayment_credits_advance_to_balance(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 50_000, 'stock' => 5]);

        $sale = $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->individual,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 80_000,
            paidCard: 0,
        );

        $this->assertSame(50_000, $sale->total);
        $this->assertSame(80_000, $sale->paid_amount);
        $this->assertSame(0, $sale->debt_amount);
        $this->assertSame(OrderPaymentStatus::PAID, $sale->payment_status);
        $this->assertSame(30_000, (int) $this->individual->fresh()->balance);
    }

    public function test_discount_applied_lowers_total(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 100_000, 'stock' => 5]);

        $sale = $this->sales->create(
            shift: $shift,
            cashier: $this->cashier,
            customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 90_000,
            paidCard: 0,
            discount: 10_000,
        );

        $this->assertSame(90_000, $sale->total);
        $this->assertSame(10_000, (int) $sale->discount);
    }

    public function test_receipt_numbers_are_unique_per_shift_for_sequential_sales(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 100]);

        $first = $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 10_000, paidCard: 0,
        );
        $second = $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 10_000, paidCard: 0,
        );
        $third = $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 10_000, paidCard: 0,
        );

        $numbers = [$first->receipt_number, $second->receipt_number, $third->receipt_number];
        $this->assertCount(3, array_unique($numbers));
        $this->assertSame(sprintf('%d-00001', $shift->id), $first->receipt_number);
        $this->assertSame(sprintf('%d-00003', $shift->id), $third->receipt_number);
    }

    public function test_record_customer_payment_credits_balance_and_updates_order_status(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 100_000, 'stock' => 5]);

        $sale = $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $this->individual,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 30_000, paidCard: 0,
        );
        $this->assertSame(-70_000, (int) $this->individual->fresh()->balance);

        $this->sales->recordCustomerPayment(
            order: $sale,
            amount: 70_000,
            method: PaymentMethod::CASH,
            shiftId: $shift->id,
        );

        $fresh = Order::query()->findOrFail($sale->id);
        $this->assertSame(0, (int) $fresh->debt_amount);
        $this->assertSame(100_000, (int) $fresh->paid_amount);
        $this->assertSame(OrderPaymentStatus::PAID, $fresh->payment_status);
        $this->assertSame(0, (int) $this->individual->fresh()->balance);
    }

    public function test_throws_when_sale_to_customer_of_other_dealer(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 5]);
        $otherDealer = Dealer::factory()->create();
        $foreignCustomer = Shop::factory()->for($otherDealer)->individual()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $foreignCustomer,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 10_000, paidCard: 0,
        );
    }

    public function test_card_payment_requires_cardholder_name(): void
    {
        $shift = $this->shifts->open($this->cashier, openingCash: 0);
        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'stock' => 5]);

        $this->expectException(\InvalidArgumentException::class);
        $this->sales->create(
            shift: $shift, cashier: $this->cashier, customer: $this->walkIn,
            items: [['product_id' => $product->id, 'qty' => 1]],
            paidCash: 0, paidCard: 10_000,
            cardholderName: '   ',
        );
    }
}
