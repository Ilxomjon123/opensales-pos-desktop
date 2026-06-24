<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DeliverymanSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DeliverymanLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_deliveryman_cannot_login(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create([
            'security_locked_until' => Carbon::now()->addHour(),
        ]);

        $response = $this->post(route('login.store'), [
            'username' => $deliveryman->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_unlocked_deliveryman_can_login(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create([
            'security_locked_until' => Carbon::now()->subHour(), // o'tib ketgan
        ]);

        $response = $this->post(route('login.store'), [
            'username' => $deliveryman->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($deliveryman);
        $response->assertRedirect('/dealer/routes/today');
    }

    public function test_deliveryman_login_kills_other_sessions(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        // Mavjud "boshqa qurilma" sessiyasi
        DB::table('sessions')->insert([
            'id' => 'old-session-id',
            'user_id' => $deliveryman->id,
            'ip_address' => '5.5.5.5',
            'user_agent' => 'OtherBrowser',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->post(route('login.store'), [
            'username' => $deliveryman->username,
            'password' => 'password',
        ]);

        $this->assertSame(0, DB::table('sessions')->where('id', 'old-session-id')->count());
    }

    public function test_dealer_login_does_not_kill_sessions(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        DB::table('sessions')->insert([
            'id' => 'dealer-old-session',
            'user_id' => $owner->id,
            'ip_address' => '5.5.5.5',
            'user_agent' => 'OtherBrowser',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->post(route('login.store'), [
            'username' => $owner->username,
            'password' => 'password',
        ]);

        // Dealer/Warehouse rollari uchun bir vaqtda bir nechta sessiya ruxsat
        $this->assertSame(1, DB::table('sessions')->where('id', 'dealer-old-session')->count());
    }

    public function test_super_admin_can_unlock_deliveryman(): void
    {
        $dealer = Dealer::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create([
            'security_locked_until' => Carbon::now()->addHour(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.deliverymen.unlock', $deliveryman));

        $response->assertRedirect();
        $this->assertNull($deliveryman->fresh()->security_locked_until);
    }

    public function test_dealer_cannot_unlock_their_deliveryman(): void
    {
        // Diller potentsial fraudster — qulfni o'zi ocha olmasligi shart,
        // aks holda FIXED_PER_DELIVERYMAN komissiyani aylanib o'tishi mumkin.
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);
        $deliveryman = User::factory()->deliveryman($dealer->id)->create([
            'security_locked_until' => Carbon::now()->addHour(),
        ]);

        $response = $this->actingAs($owner)
            ->post(route('admin.deliverymen.unlock', $deliveryman));

        $response->assertRedirect(route('dealer.stats.index'));
        $this->assertNotNull($deliveryman->fresh()->security_locked_until);
    }

    public function test_unlock_route_does_not_exist_for_dealer_panel(): void
    {
        $this->assertFalse(Route::has('dealer.deliverymen.unlock'));
    }

    public function test_middleware_logs_out_locked_deliveryman_on_request(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create([
            'security_locked_until' => Carbon::now()->addHour(),
        ]);

        $response = $this->actingAs($deliveryman)
            ->get(route('dealer.routes.today'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_middleware_blocks_concurrent_ip_after_threshold(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();

        $service = app(DeliverymanSecurityService::class);
        // Avvaldan 1 ta buzilish — middleware o'qida 2-buzilish soft block beradi
        $service->track($deliveryman, '1.1.1.1');
        $service->track($deliveryman, '9.9.9.9'); // 1-buzilish

        $response = $this->actingAs($deliveryman)
            ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get(route('dealer.routes.today'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
