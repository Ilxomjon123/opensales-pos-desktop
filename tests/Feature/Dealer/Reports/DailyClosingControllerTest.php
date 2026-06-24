<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\CourierSettlement;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class DailyClosingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

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
        $this->shop = Shop::factory()->for($this->dealer)->create();
    }

    public function test_owner_can_view_daily_closing(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.daily-closing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/DailyClosing')
                ->has('report.meta.date_from')
                ->has('report.orders')
                ->has('report.payments')
                ->has('report.courier_cash')
                ->has('report.returns')
                ->has('report.stock')
            );
    }

    public function test_aggregates_today_orders_and_payments(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $this->createOrder(deliveredTotal: 100_000, discount: 10_000, createdAt: $today);
        $this->createOrder(deliveredTotal: 50_000, status: OrderStatus::PENDING, createdAt: $today);

        Payment::factory()
            ->for($this->shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'amount' => 200_000,
                'type' => PaymentType::CREDIT,
                'method' => PaymentMethod::CASH,
            ]);

        Payment::factory()
            ->for($this->shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'amount' => 150_000,
                'type' => PaymentType::CREDIT,
                'method' => PaymentMethod::CARD,
            ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.daily-closing.index', [
                'date_from' => $today->format('Y-m-d'),
                'date_to' => $today->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.orders.total', 2)
                ->where('report.orders.delivered', 1)
                ->where('report.orders.pending', 1)
                ->where('report.orders.gross', 100_000)
                ->where('report.orders.discount', 10_000)
                ->where('report.orders.net', 90_000)
                ->where('report.payments.credit_cash', 200_000)
                ->where('report.payments.credit_card', 150_000)
                ->where('report.payments.total_credit', 350_000)
                ->where('report.payments.net_inflow', 350_000)
            );
    }

    public function test_courier_cash_block_lists_deliverymen(): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $courier = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
            'name' => 'Test Courier',
        ]);

        Payment::factory()
            ->for($this->shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'deliveryman_id' => $courier->id,
                'amount' => 80_000,
                'type' => PaymentType::CREDIT,
                'method' => PaymentMethod::CASH,
            ]);

        CourierSettlement::query()->create([
            'dealer_id' => $this->dealer->id,
            'deliveryman_id' => $courier->id,
            'settled_by_user_id' => $this->owner->id,
            'amount' => 30_000,
            'settled_at' => $today->addHours(2),
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.daily-closing.index', [
                'date_from' => $today->format('Y-m-d'),
                'date_to' => $today->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.courier_cash.0.deliveryman_id', $courier->id)
                ->where('report.courier_cash.0.received_today', 80_000)
                ->where('report.courier_cash.0.settled_today', 30_000)
                ->where('report.courier_cash.0.pending_balance', 50_000)
            );
    }

    public function test_returns_and_stock_blocks_count_transactions(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        Transaction::factory()->create([
            'dealer_id' => $this->dealer->id,
            'user_id' => $this->owner->id,
            'type' => TransactionType::STOCK_IN,
        ]);

        Transaction::factory()->create([
            'dealer_id' => $this->dealer->id,
            'user_id' => $this->owner->id,
            'type' => TransactionType::SHOP_RETURN,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.daily-closing.index', [
                'date_from' => $today->format('Y-m-d'),
                'date_to' => $today->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.stock.stock_in_count', 1)
                ->where('report.returns.shop_returns_count', 1)
                ->where('report.returns.supplier_returns_count', 0)
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.daily-closing.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));

        ob_start();
        $response->baseResponse->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringContainsString('BUYURTMALAR', $body);
        $this->assertStringContainsString("TO'LOVLAR", $body);
        $this->assertStringContainsString('KURYER NAQDI', $body);
    }

    public function test_deliveryman_cannot_access_daily_closing(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.daily-closing.index'))
            ->assertRedirect();
    }

    private function createOrder(
        OrderStatus $status = OrderStatus::DELIVERED,
        int $deliveredTotal = 100_000,
        int $discount = 0,
        ?CarbonImmutable $createdAt = null,
    ): Order {
        $when = $createdAt ?? CarbonImmutable::now();

        $order = Order::factory()
            ->for($this->shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'status' => $status,
                'total' => $deliveredTotal,
                'delivered_total' => $deliveredTotal,
                'discount' => $discount,
            ]);

        $order->forceFill([
            'created_at' => $when,
            'updated_at' => $when,
        ])->save();

        return $order;
    }
}
