<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ShopMembersShowTest extends TestCase
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

    public function test_show_lists_both_telegram_and_mobile_members(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        $telegram = ShopMember::factory()->for($shop)->create([
            'telegram_id' => 555_111,
            'name' => 'Telegram vakil',
        ]);

        $customer = Customer::factory()->create([
            'phone' => '+998901234567',
            'name' => 'Mobil vakil',
        ]);
        $mobile = ShopMember::factory()->for($shop)->create([
            'telegram_id' => null,
            'name' => null,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.shops.show', $shop))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dealer/Shops/Show')
                ->has('members.data', 2)
                ->where('members.data.0.id', $telegram->id)
                ->where('members.data.0.channel', 'telegram')
                ->where('members.data.0.name', 'Telegram vakil')
                ->where('members.data.1.id', $mobile->id)
                ->where('members.data.1.channel', 'mobile')
                ->where('members.data.1.name', 'Mobil vakil')
                ->where('members.data.1.phone', '+998901234567')
            );
    }

    public function test_linked_member_reports_both_channel(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $customer = Customer::factory()->create();

        ShopMember::factory()->for($shop)->create([
            'telegram_id' => 777_222,
            'customer_id' => $customer->id,
            'app_linked_at' => now(),
        ]);

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.shops.show', $shop))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.data.0.channel', 'both')
            );
    }
}
