<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_is_redirected_to_dealers_list(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('dashboard'))->assertRedirect(route('admin.dealers.index'));
    }

    public function test_dealer_is_redirected_to_stats(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('dealer.stats.index'));
    }

    public function test_deliveryman_is_redirected_to_today_route(): void
    {
        $this->actingAs(User::factory()->deliveryman()->create());

        $this->get(route('dashboard'))->assertRedirect(route('dealer.routes.today'));
    }
}
