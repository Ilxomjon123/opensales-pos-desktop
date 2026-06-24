<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BotUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $dealerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dealer = Dealer::factory()->create();
        $this->dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_view_bot_users_page_with_kpi_and_rows(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();

        $active = ShopMember::factory()->for($shop)->create([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(10),
        ]);
        $stale = ShopMember::factory()->for($shop)->create([
            'is_active' => true,
            'last_seen_at' => now()->subDays(60),
        ]);
        ShopMember::factory()->for($otherShop)->create();

        Order::factory()->for($shop)->create([
            'dealer_id' => $this->dealer->id,
            'member_id' => $active->id,
            'status' => OrderStatus::DELIVERED,
        ]);

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.bot-users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/BotUsers/Index')
                ->where('kpi.total', 2)
                ->where('kpi.never_ordered', 1)
                ->has('members.data', 2)
                ->has('shops')
            );
    }

    public function test_filter_never_ordered_returns_only_members_without_orders(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $withOrder = ShopMember::factory()->for($shop)->create();
        ShopMember::factory()->for($shop)->create();

        Order::factory()->for($shop)->create([
            'dealer_id' => $this->dealer->id,
            'member_id' => $withOrder->id,
        ]);

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.bot-users.index', ['activity' => 'never']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('members.data', 1));
    }

    public function test_search_matches_username_or_telegram_id(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        ShopMember::factory()->for($shop)->create(['username' => 'matchme', 'telegram_id' => 111111111]);
        ShopMember::factory()->for($shop)->create(['username' => 'other', 'telegram_id' => 222222222]);

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.bot-users.index', ['search' => 'matchme']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('members.data', 1));
    }

    public function test_show_returns_full_member_detail_with_recent_orders(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['balance' => -50000]);
        $member = ShopMember::factory()->for($shop)->create([
            'name' => 'Ali',
            'username' => 'ali_bot',
        ]);

        Order::factory()->for($shop)->create([
            'dealer_id' => $this->dealer->id,
            'member_id' => $member->id,
            'status' => OrderStatus::DELIVERED,
            'total' => 120000,
        ]);

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.bot-users.show', $member))
            ->assertOk()
            ->assertJson([
                'id' => $member->id,
                'name' => 'Ali',
                'username' => 'ali_bot',
                'orders_count' => 1,
                'shop' => [
                    'id' => $shop->id,
                    'balance' => -50000,
                ],
            ])
            ->assertJsonCount(1, 'recent_orders')
            ->assertJsonPath('recent_orders.0.total', 120000);
    }

    public function test_show_forbids_member_of_other_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();
        $member = ShopMember::factory()->for($otherShop)->create();

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.bot-users.show', $member))
            ->assertNotFound();
    }
}
