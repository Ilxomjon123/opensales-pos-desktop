<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopVisitTest extends TestCase
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

    public function test_dealer_can_record_a_visit_with_note(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->post(route('dealer.shops.visits.store', $shop), ['note' => 'Qarzni eslatdim'])
            ->assertRedirect();

        $this->assertDatabaseHas('shop_visits', [
            'shop_id' => $shop->id,
            'dealer_id' => $this->dealer->id,
            'user_id' => $this->user->id,
            'note' => 'Qarzni eslatdim',
        ]);
    }

    public function test_visit_note_is_required(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->post(route('dealer.shops.visits.store', $shop), [])
            ->assertSessionHasErrors('note');

        $this->assertDatabaseCount('shop_visits', 0);
    }

    public function test_cannot_record_visit_for_another_dealer_shop(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreignShop = Shop::factory()->for($otherDealer)->create();

        $this->actingAs($this->user)
            ->post(route('dealer.shops.visits.store', $foreignShop), ['note' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('shop_visits', 0);
    }

    public function test_inactive_filter_returns_shops_without_active_members(): void
    {
        // Faolsiz: bitta ham vakili yo'q
        Shop::factory()->for($this->dealer)->create(['name' => 'NoMembers']);

        // Faolsiz: yagona vakili botni bloklagan
        $blocked = Shop::factory()->for($this->dealer)->create(['name' => 'Blocked']);
        ShopMember::factory()->for($blocked)->create(['blocked_at' => now()->subDay()]);

        // Faol: faol vakili bor va yaqinda buyurtma bergan
        $active = Shop::factory()->for($this->dealer)->create(['name' => 'Active']);
        ShopMember::factory()->for($active)->create(['blocked_at' => null]);
        Order::factory()->for($active)->for($this->dealer)->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['activity' => 'inactive']))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $names = collect($rows)->pluck('name');

                    return $names->contains('NoMembers')
                        && $names->contains('Blocked')
                        && ! $names->contains('Active');
                })
            );
    }

    public function test_author_can_update_own_visit_within_window(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $author = User::factory()->deliveryman($this->dealer->id)->create();
        $visit = ShopVisit::factory()->for($shop)->for($this->dealer)->create([
            'user_id' => $author->id,
            'note' => 'eski',
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($author)
            ->put(route('dealer.shops.visits.update', [$shop, $visit]), ['note' => 'yangi'])
            ->assertRedirect();

        $this->assertSame('yangi', $visit->fresh()->note);
    }

    public function test_dealer_owner_can_delete_any_visit_within_window(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $author = User::factory()->deliveryman($this->dealer->id)->create();
        $visit = ShopVisit::factory()->for($shop)->for($this->dealer)->create([
            'user_id' => $author->id,
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->user)
            ->delete(route('dealer.shops.visits.destroy', [$shop, $visit]))
            ->assertRedirect();

        $this->assertDatabaseMissing('shop_visits', ['id' => $visit->id]);
    }

    public function test_cannot_modify_visit_after_window(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $visit = ShopVisit::factory()->for($shop)->for($this->dealer)->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subHours(5),
        ]);

        $this->actingAs($this->user)
            ->put(route('dealer.shops.visits.update', [$shop, $visit]), ['note' => 'kech'])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('dealer.shops.visits.destroy', [$shop, $visit]))
            ->assertForbidden();
    }

    public function test_non_author_deliveryman_cannot_modify_visit(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $author = User::factory()->deliveryman($this->dealer->id)->create();
        $other = User::factory()->deliveryman($this->dealer->id)->create();
        $visit = ShopVisit::factory()->for($shop)->for($this->dealer)->create([
            'user_id' => $author->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($other)
            ->put(route('dealer.shops.visits.update', [$shop, $visit]), ['note' => 'x'])
            ->assertForbidden();
    }

    public function test_cannot_modify_visit_of_another_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreignShop = Shop::factory()->for($otherDealer)->create();
        $foreignVisit = ShopVisit::factory()->for($foreignShop)->for($otherDealer)->create([
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('dealer.shops.visits.destroy', [$foreignShop, $foreignVisit]))
            ->assertForbidden();

        $this->assertDatabaseHas('shop_visits', ['id' => $foreignVisit->id]);
    }

    public function test_recent_visit_removes_shop_from_inactive(): void
    {
        // Vakilsiz, buyurtmasiz — lekin yaqinda vizit qilingan → faol
        $visited = Shop::factory()->for($this->dealer)->create(['name' => 'Visited']);
        ShopVisit::factory()->for($visited)->for($this->dealer)->create([
            'user_id' => $this->user->id,
            'visited_at' => now()->subDay(),
        ]);

        // Vakilsiz, buyurtmasiz, vizit ham eski (30 kun) → faolsiz
        $stale = Shop::factory()->for($this->dealer)->create(['name' => 'StaleVisit']);
        ShopVisit::factory()->for($stale)->for($this->dealer)->create([
            'user_id' => $this->user->id,
            'visited_at' => now()->subDays(30),
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['activity' => 'inactive', 'inactive_days' => 14]))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $names = collect($rows)->pluck('name');

                    return ! $names->contains('Visited') && $names->contains('StaleVisit');
                })
            );
    }

    public function test_inactive_filter_flags_shop_without_recent_order(): void
    {
        // Faol vakili bor, lekin oxirgi buyurtma 30 kun oldin → 14 kunlik chegarada faolsiz
        $stale = Shop::factory()->for($this->dealer)->create(['name' => 'Stale']);
        ShopMember::factory()->for($stale)->create(['blocked_at' => null]);
        Order::factory()->for($stale)->for($this->dealer)->create(['created_at' => now()->subDays(30)]);

        $fresh = Shop::factory()->for($this->dealer)->create(['name' => 'Fresh']);
        ShopMember::factory()->for($fresh)->create(['blocked_at' => null]);
        Order::factory()->for($fresh)->for($this->dealer)->create(['created_at' => now()->subDays(3)]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['activity' => 'inactive', 'inactive_days' => 14]))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $names = collect($rows)->pluck('name');

                    return $names->contains('Stale') && ! $names->contains('Fresh');
                })
            );
    }

    public function test_inactive_filter_sorts_shops_without_members_first(): void
    {
        // Vakili bor, lekin eski buyurtma → faolsiz
        $withMembers = Shop::factory()->for($this->dealer)->create(['name' => 'WithMembers']);
        ShopMember::factory()->for($withMembers)->create(['blocked_at' => null]);
        Order::factory()->for($withMembers)->for($this->dealer)->create(['created_at' => now()->subDays(40)]);

        // Vakilsiz → faolsiz va eng tepada chiqishi kerak
        $noMembers = Shop::factory()->for($this->dealer)->create(['name' => 'NoMembersTop']);

        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['activity' => 'inactive', 'inactive_days' => 14]))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $names = collect($rows)->pluck('name')->values();
                    $noPos = $names->search('NoMembersTop');
                    $withPos = $names->search('WithMembers');

                    return $noPos !== false && $withPos !== false && $noPos < $withPos;
                })
            );
    }

    public function test_active_filter_returns_only_active_shops(): void
    {
        // Faol: faol vakil + yaqinda buyurtma
        $active = Shop::factory()->for($this->dealer)->create(['name' => 'ActiveOne']);
        ShopMember::factory()->for($active)->create(['blocked_at' => null]);
        Order::factory()->for($active)->for($this->dealer)->create(['created_at' => now()->subDays(2)]);

        // Faol: vakilsiz/buyurtmasiz, lekin yaqinda vizit qilingan
        $visited = Shop::factory()->for($this->dealer)->create(['name' => 'VisitedActive']);
        ShopVisit::factory()->for($visited)->for($this->dealer)->create([
            'user_id' => $this->user->id,
            'visited_at' => now()->subDay(),
        ]);

        // Faolsiz: vakilsiz, buyurtmasiz, vizitsiz
        Shop::factory()->for($this->dealer)->create(['name' => 'Dead']);

        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['activity' => 'active', 'inactive_days' => 14]))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $names = collect($rows)->pluck('name');

                    return $names->contains('ActiveOne')
                        && $names->contains('VisitedActive')
                        && ! $names->contains('Dead');
                })
            );
    }
}
