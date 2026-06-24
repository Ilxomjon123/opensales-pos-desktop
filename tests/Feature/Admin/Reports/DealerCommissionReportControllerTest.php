<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Reports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\PlatformPayment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class DealerCommissionReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
    }

    public function test_admin_can_view_commission_report(): void
    {
        Dealer::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.commission.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Commission')
                ->has('report.summary')
                ->has('report.rows')
            );
    }

    public function test_commission_computed_from_turnover_and_rate(): void
    {
        $dealer = Dealer::factory()->create(['platform_fee_rate' => 5.0]);
        $shop = Shop::factory()->for($dealer)->create();

        // 1_000_000 aylanma × 5% = 50_000 komissiya
        $order = new Order;
        $order->forceFill([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::DELIVERED->value,
            'total' => 1_000_000,
            'delivered_total' => 1_000_000,
            'discount' => 0,
            'platform_fee_rate' => 5.0,
        ])->save();

        // To'lov: 20_000 — qarz qoladi 30_000
        $payment = new PlatformPayment;
        $payment->forceFill([
            'dealer_id' => $dealer->id,
            'amount' => 20_000,
            'discount' => 0,
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.commission.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.turnover', 1_000_000)
                ->where('report.summary.fee', 50_000)
                ->where('report.summary.paid', 20_000)
                ->where('report.summary.owed', 30_000)
            );
    }

    public function test_export_csv_stream(): void
    {
        Dealer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.commission.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    public function test_dealer_cannot_access(): void
    {
        $d = Dealer::factory()->create();
        $u = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $d->id]);

        $this->actingAs($u)
            ->get(route('admin.reports.commission.index'))
            ->assertRedirect();
    }
}
