<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Models\Dealer;
use App\Models\DealerDeliveryZone;
use App\Models\Region;
use App\Models\Shop;
use Closure;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackfillGeoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);
    }

    /**
     * Observer yangi yozuvni avto-normallashtiradi; backfill esa ESKI (FK'siz)
     * ma'lumot uchun. Shu sabab fixture'lar event'siz yaratiladi.
     */
    private function legacy(Closure $factory): mixed
    {
        return Shop::withoutEvents($factory);
    }

    public function test_backfills_shop_region_and_district_fk(): void
    {
        $dealer = Dealer::factory()->create(['country_id' => null]);
        $shop = $this->legacy(fn () => Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
            'region_id' => null,
            'district_id' => null,
            'country_id' => null,
        ]));

        $this->artisan('geo:backfill')->assertSuccessful();

        $uz = Country::query()->where('code', 'uz')->firstOrFail();
        $region = Region::query()->where('name', 'Toshkent shahri')->firstOrFail();

        $shop->refresh();
        $this->assertSame($uz->id, $shop->country_id);
        $this->assertSame($region->id, $shop->region_id);
        $this->assertNotNull($shop->district_id);

        $dealer->refresh();
        $this->assertSame($uz->id, $dealer->country_id);
    }

    public function test_backfills_delivery_zone_fk(): void
    {
        $dealer = Dealer::factory()->create(['country_id' => null]);
        $zone = DealerDeliveryZone::factory()->for($dealer)->create([
            'region' => 'Namangan viloyati',
            'district' => null,
            'region_id' => null,
        ]);

        $this->artisan('geo:backfill')->assertSuccessful();

        $region = Region::query()->where('name', 'Namangan viloyati')->firstOrFail();

        $zone->refresh();
        $this->assertSame($region->id, $zone->region_id);
        $this->assertNull($zone->district_id);
    }

    public function test_dry_run_does_not_write(): void
    {
        $dealer = Dealer::factory()->create(['country_id' => null]);
        $shop = $this->legacy(fn () => Shop::factory()->for($dealer)->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
            'region_id' => null,
        ]));

        $this->artisan('geo:backfill', ['--dry-run' => true])->assertSuccessful();

        $shop->refresh();
        $this->assertNull($shop->region_id);
    }

    public function test_is_idempotent_and_skips_already_filled(): void
    {
        $dealer = Dealer::factory()->create(['country_id' => null]);
        $this->legacy(fn () => Shop::factory()->for($dealer)->create([
            'region' => 'Buxoro viloyati',
            'region_id' => null,
        ]));

        $this->artisan('geo:backfill')->assertSuccessful();
        $this->artisan('geo:backfill')->assertSuccessful();

        $region = Region::query()->where('name', 'Buxoro viloyati')->firstOrFail();
        $this->assertSame(1, Shop::query()->where('region_id', $region->id)->count());
    }
}
