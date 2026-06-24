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

final class DealerActivityReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
    }

    public function test_admin_can_view_dealer_activity(): void
    {
        Dealer::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.dealer-activity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/DealerActivity')
                ->has('report.summary')
                ->has('report.rows')
            );
    }

    public function test_status_classification(): void
    {
        $activeDealer = Dealer::factory()->create(['name' => 'Active']);
        $atRiskDealer = Dealer::factory()->create(['name' => 'AtRisk']);
        $inactiveDealer = Dealer::factory()->create(['name' => 'Inactive']);

        $this->makeOrder($activeDealer, CarbonImmutable::now()->subDays(2));
        $this->makeOrder($atRiskDealer, CarbonImmutable::now()->subDays(15));
        $this->makeOrder($inactiveDealer, CarbonImmutable::now()->subDays(60));

        $this->actingAs($this->admin)
            ->get(route('admin.reports.dealer-activity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.dealers', 3)
                ->where('report.summary.active', 1)
                ->where('report.summary.at_risk', 1)
                ->where('report.summary.inactive', 1)
            );
    }

    public function test_status_filter(): void
    {
        $activeDealer = Dealer::factory()->create(['name' => 'Active']);
        Dealer::factory()->create(['name' => 'NeverOrdered']);

        $this->makeOrder($activeDealer, CarbonImmutable::now()->subDay());

        $this->actingAs($this->admin)
            ->get(route('admin.reports.dealer-activity.index', ['status' => 'active']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.dealers', 1)
                ->where('report.rows.0.name', 'Active')
            );
    }

    public function test_export_csv_stream(): void
    {
        Dealer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.dealer-activity.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    public function test_dealer_cannot_access(): void
    {
        $d = Dealer::factory()->create();
        $u = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $d->id]);

        $this->actingAs($u)
            ->get(route('admin.reports.dealer-activity.index'))
            ->assertRedirect();
    }

    private function makeOrder(Dealer $dealer, CarbonImmutable $createdAt): Order
    {
        $shop = Shop::factory()->for($dealer)->create();

        // Order::factory() definition phantom dealer yaratadi — uni chetlab o'tib
        // to'g'ridan-to'g'ri yozamiz.
        $order = new Order;
        $order->forceFill([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::DELIVERED->value,
            'total' => 100_000,
            'delivered_total' => 100_000,
            'discount' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }
}
