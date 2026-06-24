<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DealerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_guest_can_self_register_without_bot_token(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Olma Savdo',
            'username' => 'olma_savdo',
            'phone' => '+998 90 123 45 67',
            'commission_type' => 'fixed_per_order',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('dealer.stats.index'));
        $this->assertAuthenticated();

        $dealer = Dealer::query()->where('name', 'Olma Savdo')->firstOrFail();
        $this->assertNull($dealer->bot_token);
        $this->assertNull($dealer->bot_username);
        $this->assertTrue($dealer->is_active);
        $this->assertTrue($dealer->is_self_registered);
        $this->assertNull($dealer->onboarding_completed_at);
        $this->assertSame('+998 90 123 45 67', $dealer->contact_phone);
        $this->assertSame('fixed_per_order', $dealer->commission_type->value);
        $this->assertSame(1500, $dealer->fixed_commission_amount);
        $this->assertNotNull($dealer->trial_ends_at);
        $this->assertTrue($dealer->onTrial());

        $this->assertDatabaseHas('users', [
            'username' => 'olma_savdo',
            'phone' => '+998 90 123 45 67',
            'role' => UserRole::DEALER->value,
            'dealer_id' => $dealer->id,
        ]);
    }

    public function test_registration_requires_phone(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Olma Savdo',
            'username' => 'olma_savdo',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Olma Savdo',
            'username' => 'olma_savdo',
            'password' => 'secret123',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'olma_savdo']);
    }

    public function test_registration_rejects_duplicate_username(): void
    {
        $dealer = Dealer::factory()->create();
        User::factory()->create(['username' => 'taken_user', 'dealer_id' => $dealer->id]);

        $this->post(route('register.store'), [
            'name' => 'Boshqa',
            'username' => 'taken_user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('username');
    }

    public function test_authenticated_user_cannot_view_register(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['dealer_id' => $dealer->id]);

        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }

    public function test_onboarding_can_be_completed(): void
    {
        $dealer = Dealer::factory()->create(['onboarding_completed_at' => null]);
        $user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($user)
            ->post(route('dealer.onboarding.complete'))
            ->assertRedirect();

        $this->assertNotNull($dealer->fresh()->onboarding_completed_at);
    }
}
