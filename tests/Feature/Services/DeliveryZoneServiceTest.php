<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Services\DeliveryZoneService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeliveryZoneServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryZoneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Butun-viloyat kengaytirishi DB geo katalogiga tayanadi.
        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);
        $this->service = app(DeliveryZoneService::class);
    }

    public function test_covers_everywhere_when_no_zones(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertTrue($this->service->covers($dealer, 'Samarqand viloyati', 'Urgut tumani'));
        $this->assertTrue($this->service->covers($dealer, null, null));
    }

    public function test_covers_only_matching_district(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $this->assertTrue($this->service->covers($dealer, 'Toshkent shahri', 'Chilonzor tumani'));
        $this->assertFalse($this->service->covers($dealer, 'Toshkent shahri', 'Yunusobod tumani'));
        $this->assertFalse($this->service->covers($dealer, 'Samarqand viloyati', 'Urgut tumani'));
    }

    public function test_whole_region_covers_all_districts(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => null]);

        $this->assertTrue($this->service->covers($dealer, 'Toshkent shahri', 'Yunusobod tumani'));
        $this->assertTrue($this->service->covers($dealer, 'Toshkent shahri', 'Chilonzor tumani'));
        $this->assertFalse($this->service->covers($dealer, 'Andijon viloyati', 'Asaka tumani'));
    }

    public function test_unknown_region_is_not_blocked(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $this->assertTrue($this->service->covers($dealer, null, null));
    }

    public function test_selection_expands_whole_region_to_all_districts(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => null]);
        $dealer->deliveryZones()->create(['region' => 'Andijon viloyati', 'district' => 'Asaka tumani']);

        $selection = collect($this->service->selectionForDealer($dealer))->keyBy('region');

        $this->assertTrue($selection['Toshkent shahri']['whole_region']);
        $this->assertContains('Chilonzor tumani', $selection['Toshkent shahri']['districts']);
        $this->assertFalse($selection['Andijon viloyati']['whole_region']);
        $this->assertSame(['Asaka tumani'], $selection['Andijon viloyati']['districts']);
    }
}
