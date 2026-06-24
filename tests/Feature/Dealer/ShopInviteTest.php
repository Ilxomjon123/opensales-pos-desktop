<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopInviteTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dealer = Dealer::factory()->create();
    }

    public function test_dealer_can_create_invite_for_any_shop(): void
    {
        $dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
        $shop = Shop::factory()->for($this->dealer)->create();

        $this->actingAs($dealerUser)
            ->post(route('dealer.shops.invite', $shop))
            ->assertRedirect();

        $this->assertDatabaseHas('shop_invites', ['shop_id' => $shop->id]);
    }

    public function test_deliveryman_can_invite_shop_not_assigned_to_them(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        // Shop deliveryman'ga biriktirilmagan (deliveryman_id null).
        $shop = Shop::factory()->for($this->dealer)->create([
            'deliveryman_id' => null,
        ]);

        $this->actingAs($deliveryman)
            ->post(route('dealer.shops.invite', $shop))
            ->assertRedirect();

        $this->assertDatabaseHas('shop_invites', ['shop_id' => $shop->id]);
    }

    public function test_deliveryman_cannot_invite_shop_of_other_dealer(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        $otherDealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($otherDealer)->create();

        $this->actingAs($deliveryman)
            ->post(route('dealer.shops.invite', $shop))
            ->assertForbidden();

        $this->assertDatabaseMissing('shop_invites', ['shop_id' => $shop->id]);
    }
}
