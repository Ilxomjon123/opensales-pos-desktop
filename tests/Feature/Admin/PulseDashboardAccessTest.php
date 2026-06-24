<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PulseDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_pulse_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);

        $this->actingAs($admin)
            ->get('/pulse')
            ->assertOk();
    }

    public function test_dealer_cannot_view_pulse_dashboard(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);

        $this->actingAs($user)
            ->get('/pulse')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_pulse_dashboard(): void
    {
        $this->get('/pulse')->assertForbidden();
    }
}
