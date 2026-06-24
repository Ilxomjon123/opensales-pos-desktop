<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cannot_access_shops(): void
    {
        $this->get(route('admin.shops.index'))->assertRedirect(route('login'));
    }

    public function test_dealer_cannot_access_shops(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.shops.index'))
            ->assertRedirect(route('dealer.stats.index'));
    }

    public function test_shops_with_same_inn_across_dealers_are_grouped(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $dealerA = Dealer::factory()->create(['name' => 'Dealer A']);
        $dealerB = Dealer::factory()->create(['name' => 'Dealer B']);
        $dealerC = Dealer::factory()->create(['name' => 'Dealer C']);

        // Bitta INN — ikki dillerda ikki yozuv
        Shop::factory()->for($dealerA)->create(['inn' => '123456789', 'balance' => -50_000]);
        Shop::factory()->for($dealerB)->create(['inn' => '123456789', 'balance' => -30_000]);

        // Boshqa INN lar — har biri alohida
        Shop::factory()->for($dealerC)->create(['inn' => '987654321', 'balance' => 10_000]);
        Shop::factory()->for($dealerA)->create(['inn' => '555555555', 'balance' => 5_000]);

        $response = $this->actingAs($admin)
            ->get(route('admin.shops.index'))
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(4, $props['totals']['shop_rows']);
        $this->assertSame(3, $props['totals']['unique_shops']);
        $this->assertSame(1, $props['totals']['shared_inn_groups']);
        $this->assertSame(-65_000, $props['totals']['total_balance']);
        $this->assertSame(-80_000, $props['totals']['total_debt']);
        $this->assertSame(15_000, $props['totals']['total_credit']);

        $groups = $props['groups']['data'];
        $this->assertCount(3, $groups);

        $sharedGroup = collect($groups)->firstWhere('inn', '123456789');
        $this->assertNotNull($sharedGroup);
        $this->assertSame(2, $sharedGroup['dealer_count']);
        $this->assertSame(-80_000, $sharedGroup['total_balance']);
        $this->assertCount(2, $sharedGroup['dealers']);
    }

    public function test_dealer_balances_aggregate_per_dealer(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $dealer = Dealer::factory()->create(['name' => 'Dealer X']);
        Shop::factory()->for($dealer)->create(['balance' => -20_000]);
        Shop::factory()->for($dealer)->create(['balance' => -10_000]);
        Shop::factory()->for($dealer)->create(['balance' => 5_000]);

        $response = $this->actingAs($admin)
            ->get(route('admin.shops.index'))
            ->assertOk();

        $balances = $response->viewData('page')['props']['dealerBalances'];
        $row = collect($balances)->firstWhere('id', $dealer->id);

        $this->assertSame(3, $row['shops_count']);
        $this->assertSame(-30_000, $row['debt']);
        $this->assertSame(5_000, $row['credit']);
        $this->assertSame(-25_000, $row['total_balance']);
    }

    public function test_search_filters_groups(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $dealer = Dealer::factory()->create();
        Shop::factory()->for($dealer)->create(['name' => 'Alpha Store', 'inn' => '111111111']);
        Shop::factory()->for($dealer)->create(['name' => 'Beta Store', 'inn' => '222222222']);

        $response = $this->actingAs($admin)
            ->get(route('admin.shops.index', ['search' => 'Alpha']))
            ->assertOk();

        $groups = $response->viewData('page')['props']['groups']['data'];
        $this->assertCount(1, $groups);
        $this->assertSame('111111111', $groups[0]['inn']);
    }

    public function test_dealer_filter_restricts_groups(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        Shop::factory()->for($dealerA)->create(['inn' => '111111111']);
        Shop::factory()->for($dealerB)->create(['inn' => '222222222']);

        $response = $this->actingAs($admin)
            ->get(route('admin.shops.index', ['dealer_id' => $dealerA->id]))
            ->assertOk();

        $groups = $response->viewData('page')['props']['groups']['data'];
        $this->assertCount(1, $groups);
        $this->assertSame('111111111', $groups[0]['inn']);
    }
}
