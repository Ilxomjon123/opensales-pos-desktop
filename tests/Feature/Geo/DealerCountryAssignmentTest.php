<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Enums\Currency;
use App\Enums\UserRole;
use App\Models\Country;
use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DealerCountryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
    }

    public function test_self_register_assigns_russia_country_and_ruble_currency(): void
    {
        $ru = Country::query()->where('code', 'ru')->firstOrFail();

        $this->post(route('register.store'), [
            'name' => 'Russkiy Magazin',
            'username' => 'russkiy_magazin',
            'phone' => '+7 999 123 45 67',
            'country_id' => $ru->id,
            'commission_type' => 'fixed_per_order',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('dealer.stats.index'));

        $dealer = Dealer::query()->where('name', 'Russkiy Magazin')->firstOrFail();
        $this->assertSame($ru->id, $dealer->country_id);
        $this->assertSame(Currency::RUB, $dealer->currency);
    }

    public function test_self_register_defaults_to_uzbekistan_uzs(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Olma Savdo',
            'username' => 'olma_savdo',
            'phone' => '+998 90 123 45 67',
            'commission_type' => 'fixed_per_order',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('dealer.stats.index'));

        $dealer = Dealer::query()->where('name', 'Olma Savdo')->firstOrFail();
        $uz = Country::query()->where('code', 'uz')->firstOrFail();
        $this->assertSame($uz->id, $dealer->country_id);
        $this->assertSame(Currency::UZS, $dealer->currency);
    }

    public function test_admin_can_create_russia_dealer(): void
    {
        $ru = Country::query()->where('code', 'ru')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $this->actingAs($admin)->post(route('admin.dealers.store'), [
            'name' => 'RU Diller',
            'username' => 'ru_diller',
            'password' => 'secret123',
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ',
            'country_id' => $ru->id,
        ]);

        $dealer = Dealer::query()->where('name', 'RU Diller')->firstOrFail();
        $this->assertSame($ru->id, $dealer->country_id);
        $this->assertSame(Currency::RUB, $dealer->currency);
    }

    public function test_admin_can_create_dealer_without_bot_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $this->actingAs($admin)->post(route('admin.dealers.store'), [
            'name' => 'Tokensiz Diller',
            'username' => 'tokensiz_diller',
            'password' => 'secret123',
        ])->assertSessionHasNoErrors();

        $dealer = Dealer::query()->where('name', 'Tokensiz Diller')->firstOrFail();
        $this->assertNull($dealer->bot_token);
        $this->assertNull($dealer->bot_username);
    }
}
