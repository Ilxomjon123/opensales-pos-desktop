<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Contracts\ReverseGeocoderInterface;
use App\Enums\FeatureFlag;
use App\Models\Country;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class MobileConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_returns_all_flags_enabled_by_default(): void
    {
        Country::factory()->create(['code' => 'uz']);

        $this->getJson('/api/mobile/config?country=uz')
            ->assertOk()
            ->assertJson([
                'country' => 'uz',
                'features' => [
                    'phoneLoginEnabled' => true,
                    'telegramLoginEnabled' => true,
                    'qrLoginEnabled' => true,
                ],
            ]);
    }

    public function test_config_reflects_per_country_toggle(): void
    {
        Country::factory()->create(['code' => 'uz']);
        Country::factory()->russia()->create();

        app(FeatureFlagService::class)
            ->setForCountry('ru', FeatureFlag::PHONE_LOGIN, false);

        // Rossiyada telefon kirish o'chiq.
        $this->getJson('/api/mobile/config?country=ru')
            ->assertOk()
            ->assertJsonPath('features.phoneLoginEnabled', false)
            ->assertJsonPath('features.telegramLoginEnabled', true);

        // O'zbekistonda — o'zgarmagan (yoqilgan).
        $this->getJson('/api/mobile/config?country=uz')
            ->assertOk()
            ->assertJsonPath('features.phoneLoginEnabled', true);
    }

    public function test_config_resolves_country_from_coordinates(): void
    {
        Country::factory()->create(['code' => 'uz']);
        Country::factory()->russia()->create();

        // GeoResolver final — uning ichidagi geokoderini mock qilamiz.
        $geocoder = Mockery::mock(ReverseGeocoderInterface::class);
        $geocoder->shouldReceive('reverse')->once()->andReturn([
            'region' => null, 'district' => null, 'address' => null, 'country_code' => 'ru',
        ]);
        $this->app->instance(ReverseGeocoderInterface::class, $geocoder);

        $this->getJson('/api/mobile/config?lat=55.75&lng=37.61')
            ->assertOk()
            ->assertJsonPath('country', 'ru');
    }

    public function test_config_falls_back_to_default_country_when_unknown(): void
    {
        Country::factory()->create(['code' => 'uz', 'sort' => 0]);

        $this->getJson('/api/mobile/config?country=zz')
            ->assertOk()
            ->assertJsonPath('country', 'uz');
    }
}
