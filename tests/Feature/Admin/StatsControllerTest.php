<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cannot_access_stats(): void
    {
        $this->get(route('admin.stats.index'))->assertRedirect(route('login'));
    }

    public function test_dealer_cannot_access_stats(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.stats.index'))
            ->assertRedirect(route('dealer.stats.index'));
    }

    public function test_super_admin_can_view_stats(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        Order::factory()->for($dealer)->for($shop)->count(5)->create([
            'status' => OrderStatus::DELIVERED,
            'total' => 100_000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.stats.index'))
            ->assertOk();
    }

    public function test_stats_returns_chart_data(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.stats.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Stats/Index')
                ->has('chart', 30)
                ->has('totals')
                ->has('topDealers')
            );
    }

    public function test_revenue_grouped_by_currency(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $uzDealer = Dealer::factory()->create(['currency' => 'UZS']);
        $uzShop = Shop::factory()->for($uzDealer)->create();
        Order::factory()->for($uzDealer)->for($uzShop)->create([
            'status' => OrderStatus::DELIVERED,
            'currency' => 'UZS',
            'delivered_total' => 500_000,
            'discount' => 0,
        ]);

        $ruDealer = Dealer::factory()->create(['currency' => 'RUB']);
        $ruShop = Shop::factory()->for($ruDealer)->create();
        Order::factory()->for($ruDealer)->for($ruShop)->create([
            'status' => OrderStatus::DELIVERED,
            'currency' => 'RUB',
            'delivered_total' => 3_000,
            'discount' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.stats.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('totals.by_currency', 2)
                ->where('totals.by_currency', fn ($rows) => collect($rows)->contains(
                    fn ($r) => $r['currency'] === 'UZS' && $r['revenue'] === 500_000
                ) && collect($rows)->contains(
                    fn ($r) => $r['currency'] === 'RUB' && $r['revenue'] === 3_000
                ))
            );
    }
}
