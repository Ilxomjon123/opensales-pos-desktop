<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FeatureFlag;
use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeatureFlagSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
        Country::factory()->create(['code' => 'uz']);
        Country::factory()->russia()->create();
    }

    public function test_super_admin_can_view_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/settings')
            ->assertOk();
    }

    public function test_super_admin_can_disable_flag_for_a_country(): void
    {
        $this->actingAs($this->admin)
            ->patch('/admin/settings/flags', [
                'country' => 'ru',
                'flag' => FeatureFlag::PHONE_LOGIN->value,
                'enabled' => false,
            ])
            ->assertRedirect();

        $flags = app(FeatureFlagService::class);
        $this->assertFalse($flags->forCountry('ru')['phone-login']);
        // Boshqa davlatga ta'sir qilmaydi.
        $this->assertTrue($flags->forCountry('uz')['phone-login']);
    }

    public function test_update_validates_country_and_flag(): void
    {
        $this->actingAs($this->admin)
            ->patch('/admin/settings/flags', [
                'country' => 'zz',
                'flag' => 'unknown-flag',
                'enabled' => true,
            ])
            ->assertSessionHasErrors(['country', 'flag']);
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $dealer = User::factory()->create(['role' => UserRole::DEALER]);

        // EnsureSuperAdmin — super admin bo'lmagan foydalanuvchini yo'naltiradi.
        $this->actingAs($dealer)
            ->get('/admin/settings')
            ->assertRedirect();

        $this->actingAs($dealer)
            ->patch('/admin/settings/flags', [
                'country' => 'uz',
                'flag' => FeatureFlag::PHONE_LOGIN->value,
                'enabled' => false,
            ])
            ->assertRedirect();

        $this->assertTrue(app(FeatureFlagService::class)->forCountry('uz')['phone-login']);
    }
}
