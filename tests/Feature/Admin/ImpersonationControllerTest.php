<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class ImpersonationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_impersonates_dealer_owner_not_staff(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dealer = Dealer::factory()->create();

        $warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $dealer->id,
        ]);
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();
        $owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.dealers.impersonate', $dealer))
            ->assertRedirect(route('dealer.orders.index'));

        $this->assertSame($owner->id, Auth::id());
        $this->assertNotSame($warehouse->id, Auth::id());
        $this->assertNotSame($deliveryman->id, Auth::id());
    }

    public function test_returns_back_when_dealer_has_no_owner(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dealer = Dealer::factory()->create();

        User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.dealers.index'))
            ->post(route('admin.dealers.impersonate', $dealer))
            ->assertRedirect(route('admin.dealers.index'))
            ->assertSessionHas('error');

        $this->assertSame($admin->id, Auth::id());
    }
}
