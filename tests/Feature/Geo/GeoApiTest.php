<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Models\Region;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GeoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);
    }

    public function test_lists_active_countries_with_currency(): void
    {
        $this->getJson('/api/geo/countries')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'phone_prefix', 'phone_digits', 'currency', 'currency_symbol']]])
            ->assertJsonFragment(['code' => 'uz', 'currency' => 'UZS', 'phone_prefix' => '+998']);
    }

    public function test_lists_regions_for_country(): void
    {
        $uz = Country::query()->where('code', 'uz')->firstOrFail();

        $this->getJson("/api/geo/countries/{$uz->id}/regions")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Toshkent shahri']);
    }

    public function test_lists_districts_for_region(): void
    {
        $region = Region::query()->where('name', 'Toshkent shahri')->firstOrFail();

        $this->getJson("/api/geo/regions/{$region->id}/districts")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Chilonzor tumani']);
    }
}
