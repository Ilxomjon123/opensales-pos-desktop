<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Reports;

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

final class PlatformSalesReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
    }

    public function test_admin_can_view_platform_sales(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Sales')
                ->has('report.summary')
                ->has('filters')
            );
    }

    public function test_aggregates_across_dealers(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();

        $this->createOrder($dealerA, 100_000);
        $this->createOrder($dealerB, 50_000);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.orders', 2)
                ->where('report.summary.net', 150_000)
            );
    }

    public function test_group_by_dealer(): void
    {
        $dealerA = Dealer::factory()->create(['name' => 'Big']);
        $dealerB = Dealer::factory()->create(['name' => 'Small']);

        $this->createOrder($dealerA, 500_000);
        $this->createOrder($dealerB, 50_000);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.sales.index', ['group_by' => 'dealer']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.rows.0.label', 'Big')
                ->where('report.rows.0.net', 500_000)
            );
    }

    public function test_dealer_filter(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();

        $this->createOrder($dealerA, 100_000);
        $this->createOrder($dealerB, 999_999);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.sales.index', ['dealer_id' => $dealerA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.orders', 1)
                ->where('report.summary.net', 100_000)
            );
    }

    public function test_export_csv_stream(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.sales.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    public function test_dealer_cannot_access(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);

        $this->actingAs($user)
            ->get(route('admin.reports.sales.index'))
            ->assertRedirect();
    }

    private function createOrder(Dealer $dealer, int $deliveredTotal): Order
    {
        $shop = Shop::factory()->for($dealer)->create();

        $order = new Order;
        $order->forceFill([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::DELIVERED->value,
            'total' => $deliveredTotal,
            'delivered_total' => $deliveredTotal,
            'discount' => 0,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ])->save();

        return $order;
    }
}
