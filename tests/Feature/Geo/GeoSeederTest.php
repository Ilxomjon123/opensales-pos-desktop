<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use App\Support\UzRegions;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GeoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);
    }

    public function test_seeds_uzbekistan_country(): void
    {
        $uz = Country::query()->where('code', 'uz')->first();

        $this->assertNotNull($uz);
        $this->assertSame('+998', $uz->phone_prefix);
        $this->assertSame(9, $uz->phone_digits);
        $this->assertSame('UZS', $uz->currency->value);
    }

    public function test_seeds_all_uz_regions_and_districts(): void
    {
        $uz = Country::query()->where('code', 'uz')->firstOrFail();

        $expectedRegions = count(UzRegions::all());
        $expectedDistricts = collect(UzRegions::all())->sum(fn ($r) => count($r['districts']));

        $this->assertSame($expectedRegions, Region::query()->where('country_id', $uz->id)->count());
        $this->assertSame(
            $expectedDistricts,
            District::query()->whereHas('region', fn ($q) => $q->where('country_id', $uz->id))->count(),
        );
    }

    public function test_toshkent_city_has_twelve_districts(): void
    {
        $region = Region::query()->where('name', 'Toshkent shahri')->firstOrFail();

        $this->assertSame(12, $region->districts()->count());
    }

    public function test_region_and_district_aliases_are_seeded(): void
    {
        $region = Region::query()->where('name', 'Toshkent shahri')->firstOrFail();
        $district = District::query()->where('name', 'Chilonzor tumani')->firstOrFail();

        // Kanonik nom + xom aliaslar normallashtirilgan holda mavjud.
        $this->assertTrue($region->aliases()->where('alias', 'ташкент')->exists());
        $this->assertTrue($district->aliases()->where('alias', 'чиланзарскийрайон')->exists());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(UzGeoSeeder::class);

        $uz = Country::query()->where('code', 'uz')->firstOrFail();

        $this->assertSame(count(UzRegions::all()), Region::query()->where('country_id', $uz->id)->count());
    }
}
