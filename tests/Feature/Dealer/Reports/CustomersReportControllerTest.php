<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class CustomersReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

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

    public function test_owner_can_view_customers_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/Customers')
                ->has('report.summary')
                ->has('report.rows')
                ->has('filters')
                ->has('activityOptions')
            );
    }

    public function test_abc_classification_assigns_tiers_by_revenue(): void
    {
        // Bitta katta mijoz (A class), ikkita o'rtacha (B/C) — kumulyativ taqsimot tekshiruvi.
        $shopA = Shop::factory()->for($this->dealer)->create(['name' => 'Big Customer']);
        $shopB = Shop::factory()->for($this->dealer)->create(['name' => 'Medium Customer']);
        $shopC = Shop::factory()->for($this->dealer)->create(['name' => 'Small Customer']);

        $this->makeOrder($shopA, 850_000);
        $this->makeOrder($shopB, 100_000);
        $this->makeOrder($shopC, 50_000);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.shops', 3)
                ->where('report.summary.a_count', 1)
                ->where('report.rows.0.name', 'Big Customer')
                ->where('report.rows.0.tier', 'A')
                ->where('report.rows.0.net', 850_000)
            );
    }

    public function test_activity_classification_based_on_last_order(): void
    {
        $activeShop = Shop::factory()->for($this->dealer)->create(['name' => 'Active']);
        $atRiskShop = Shop::factory()->for($this->dealer)->create(['name' => 'AtRisk']);
        $inactiveShop = Shop::factory()->for($this->dealer)->create(['name' => 'Inactive']);

        $this->makeOrder($activeShop, 100_000, createdAt: CarbonImmutable::now()->subDays(3));
        $this->makeOrder($atRiskShop, 100_000, createdAt: CarbonImmutable::now()->subDays(20));
        $this->makeOrder($inactiveShop, 100_000, createdAt: CarbonImmutable::now()->subDays(60));

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.index', [
                'date_from' => CarbonImmutable::now()->subDays(90)->format('Y-m-d'),
                'date_to' => CarbonImmutable::now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.shops', 3)
                ->where('report.summary.active_count', 1)
                ->where('report.summary.at_risk_count', 1)
                ->where('report.summary.inactive_count', 1)
            );
    }

    public function test_activity_filter_returns_only_matching(): void
    {
        $activeShop = Shop::factory()->for($this->dealer)->create(['name' => 'Active']);
        $inactiveShop = Shop::factory()->for($this->dealer)->create(['name' => 'Inactive']);

        $this->makeOrder($activeShop, 100_000, createdAt: CarbonImmutable::now()->subDays(2));
        $this->makeOrder($inactiveShop, 100_000, createdAt: CarbonImmutable::now()->subDays(60));

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.index', [
                'date_from' => CarbonImmutable::now()->subDays(90)->format('Y-m-d'),
                'date_to' => CarbonImmutable::now()->format('Y-m-d'),
                'activity' => 'active',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.shops', 1)
                ->where('report.rows.0.name', 'Active')
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $this->makeOrder($shop, 50_000);

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_cross_dealer_isolation(): void
    {
        $otherDealer = Dealer::factory()->create();
        Shop::factory()->for($otherDealer)->create(['name' => 'Hidden']);
        Shop::factory()->for($this->dealer)->create(['name' => 'Visible']);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.shops', 1)
                ->where('report.rows.0.name', 'Visible')
            );
    }

    public function test_deliveryman_cannot_access_customers_report(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.customers.index'))
            ->assertRedirect();
    }

    private function makeOrder(Shop $shop, int $deliveredTotal, ?CarbonImmutable $createdAt = null): Order
    {
        $when = $createdAt ?? CarbonImmutable::now();

        $order = Order::factory()
            ->for($shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'status' => OrderStatus::DELIVERED,
                'total' => $deliveredTotal,
                'delivered_total' => $deliveredTotal,
                'discount' => 0,
            ]);

        $order->forceFill([
            'created_at' => $when,
            'updated_at' => $when,
        ])->save();

        return $order;
    }
}
