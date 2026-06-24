<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeliverymanRouteRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliveryman_cannot_access_products_page(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/products')
            ->assertRedirect(route('dealer.routes.today'));
    }

    public function test_deliveryman_cannot_access_categories_page(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/categories')
            ->assertRedirect(route('dealer.routes.today'));
    }

    public function test_deliveryman_cannot_access_finance_page(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/finance')
            ->assertRedirect(route('dealer.routes.today'));
    }

    public function test_deliveryman_can_access_routes_today(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/routes/today')
            ->assertOk();
    }

    public function test_deliveryman_can_access_orders_index(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/orders')
            ->assertOk();
    }

    public function test_deliveryman_can_access_shops_index(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->actingAs($deliveryman)
            ->get('/dealer/shops')
            ->assertOk();
    }

    public function test_owner_can_still_access_products_page(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create(['dealer_id' => $dealer->id]);

        $this->actingAs($owner)
            ->get('/dealer/products')
            ->assertOk();
    }

    public function test_login_redirects_deliveryman_home_ignoring_intended(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $this->withSession(['url.intended' => url('/dealer/products')]);

        $response = $this->post(route('login.store'), [
            'username' => $deliveryman->username,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dealer/routes/today');
    }
}
