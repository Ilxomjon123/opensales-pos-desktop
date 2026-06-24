<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Models\Dealer;
use App\Models\Region;
use App\Models\Shop;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopGeoNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);
    }

    public function test_observer_sets_fk_on_create(): void
    {
        $uz = Country::query()->where('code', 'uz')->firstOrFail();
        $dealer = Dealer::factory()->create(['country_id' => $uz->id]);

        $shop = Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
        ]);

        $region = Region::query()->where('name', 'Toshkent shahri')->firstOrFail();

        $this->assertSame($uz->id, $shop->country_id);
        $this->assertSame($region->id, $shop->region_id);
        $this->assertNotNull($shop->district_id);
    }

    public function test_observer_updates_fk_when_region_changes(): void
    {
        $uz = Country::query()->where('code', 'uz')->firstOrFail();
        $dealer = Dealer::factory()->create(['country_id' => $uz->id]);
        $shop = Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
        ]);

        $shop->update(['region' => 'Buxoro viloyati', 'district' => 'Buxoro shahri']);

        $region = Region::query()->where('name', 'Buxoro viloyati')->firstOrFail();
        $this->assertSame($region->id, $shop->fresh()->region_id);
    }

    public function test_no_geo_seed_does_not_break_save(): void
    {
        // Geo seed bo'lmasa ham shop saqlanadi (FK null qoladi).
        $dealer = Dealer::factory()->create(['country_id' => null]);
        Country::query()->delete();

        $shop = Shop::factory()->for($dealer)->create(['region' => 'Negdir', 'district' => null]);

        $this->assertNull($shop->region_id);
    }
}
