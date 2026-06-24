<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ShopBalanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_view_shops_balance(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'A do\'kon']);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/ShopsBalance/Index')
                ->where('filters.date', now()->toDateString())
                ->has('shops.data', 1)
            );
    }

    public function test_default_date_is_today_and_includes_today_payments(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        Payment::factory()->for($this->dealer)->for($shop)->credit()->create(['amount' => 200_000]);
        Payment::factory()->for($this->dealer)->for($shop)->debit()->create(['amount' => 50_000]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index'))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data.0.balance', 150_000)
                ->where('totals.net', 150_000)
                ->where('totals.credits', 200_000)
                ->where('totals.debits', 50_000)
            );
    }

    public function test_historical_date_excludes_later_payments(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        Payment::factory()->for($this->dealer)->for($shop)->credit()->create([
            'amount' => 100_000,
            'created_at' => Carbon::parse('2026-01-10 12:00:00'),
        ]);

        Payment::factory()->for($this->dealer)->for($shop)->credit()->create([
            'amount' => 999_000,
            'created_at' => Carbon::parse('2026-02-15 12:00:00'),
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index', ['date' => '2026-01-31']))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data.0.balance', 100_000)
                ->where('totals.net', 100_000)
            );
    }

    public function test_balance_includes_end_of_selected_day(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        Payment::factory()->for($this->dealer)->for($shop)->credit()->create([
            'amount' => 70_000,
            'created_at' => Carbon::parse('2026-03-05 23:30:00'),
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index', ['date' => '2026-03-05']))
            ->assertInertia(fn ($page) => $page->where('shops.data.0.balance', 70_000));
    }

    public function test_other_dealers_shops_excluded(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'My shop']);
        $otherDealer = Dealer::factory()->create();
        Shop::factory()->for($otherDealer)->create(['name' => 'Other shop']);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index'))
            ->assertInertia(fn ($page) => $page
                ->has('shops.data', 1)
                ->where('shops.data.0.name', 'My shop')
            );
    }

    public function test_search_filters_by_shop_name(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'Alpha market']);
        Shop::factory()->for($this->dealer)->create(['name' => 'Beta store']);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index', ['search' => 'alpha']))
            ->assertInertia(fn ($page) => $page
                ->has('shops.data', 1)
                ->where('shops.data.0.name', 'Alpha market')
            );
    }

    public function test_invalid_date_falls_back_to_today(): void
    {
        Shop::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index', ['date' => 'not-a-date']))
            ->assertInertia(fn ($page) => $page->where('filters.date', now()->toDateString()));
    }

    public function test_guest_cannot_access(): void
    {
        $this->get(route('dealer.shops-balance.index'))->assertRedirect(route('login'));
    }
}
