<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\DirectoryShop;
use App\Services\DirectoryShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectoryShopServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryShopService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectoryShopService;
    }

    public function test_normalize_phone_keeps_last_9_digits(): void
    {
        $this->assertSame('901234567', DirectoryShopService::normalizePhone('+998 90 123-45-67'));
        $this->assertSame('123456789', DirectoryShopService::normalizePhone('123456789'));
        $this->assertNull(DirectoryShopService::normalizePhone('12345'));
        $this->assertNull(DirectoryShopService::normalizePhone(null));
    }

    public function test_matches_by_inn_first(): void
    {
        $entry = DirectoryShop::factory()->create(['inn' => '123456789']);

        $match = $this->service->findMatch(
            inn: '123456789', phone: null, name: 'Boshqa nom',
            region: 'X', district: 'Y', latitude: null, longitude: null,
        );

        $this->assertNotNull($match);
        $this->assertSame($entry->id, $match->id);
    }

    public function test_inn_present_but_unmatched_returns_null_even_if_phone_matches(): void
    {
        DirectoryShop::factory()->create([
            'inn' => '111111111',
            'phone_normalized' => '901234567',
        ]);

        $match = $this->service->findMatch(
            inn: '999999999', phone: '+998 90 123-45-67', name: null,
            region: null, district: null, latitude: null, longitude: null,
        );

        // INN bor — telefon mosligi e'tiborga olinmaydi.
        $this->assertNull($match);
    }

    public function test_matches_by_phone_when_inn_null(): void
    {
        $entry = DirectoryShop::factory()->create([
            'inn' => null,
            'phone_normalized' => '901234567',
        ]);

        $match = $this->service->findMatch(
            inn: null, phone: '90 123 45 67', name: null,
            region: null, district: null, latitude: null, longitude: null,
        );

        $this->assertNotNull($match);
        $this->assertSame($entry->id, $match->id);
    }

    public function test_matches_by_name_region_district_when_inn_and_phone_null(): void
    {
        $entry = DirectoryShop::factory()->create([
            'inn' => null,
            'phone' => null,
            'phone_normalized' => null,
            'name' => 'Korzinka',
            'region' => 'Toshkent shahri',
            'district' => 'Yunusobod tumani',
            'latitude' => null,
            'longitude' => null,
        ]);

        $match = $this->service->findMatch(
            inn: null, phone: null, name: 'korzinka',
            region: 'Toshkent shahri', district: 'Yunusobod tumani',
            latitude: null, longitude: null,
        );

        $this->assertNotNull($match);
        $this->assertSame($entry->id, $match->id);
    }

    public function test_same_name_district_but_far_coordinates_are_different_branches(): void
    {
        DirectoryShop::factory()->create([
            'inn' => null,
            'phone' => null,
            'phone_normalized' => null,
            'name' => 'Korzinka',
            'region' => 'Toshkent shahri',
            'district' => 'Yunusobod tumani',
            'latitude' => 41.3500000,
            'longitude' => 69.2800000,
        ]);

        // ~3 km uzoqdagi bir nomli filial — alohida biznes.
        $match = $this->service->findMatch(
            inn: null, phone: null, name: 'Korzinka',
            region: 'Toshkent shahri', district: 'Yunusobod tumani',
            latitude: 41.3800000, longitude: 69.3100000,
        );

        $this->assertNull($match);
    }

    public function test_same_name_district_and_near_coordinates_match(): void
    {
        $entry = DirectoryShop::factory()->create([
            'inn' => null,
            'phone' => null,
            'phone_normalized' => null,
            'name' => 'Korzinka',
            'region' => 'Toshkent shahri',
            'district' => 'Yunusobod tumani',
            'latitude' => 41.3500000,
            'longitude' => 69.2800000,
        ]);

        // ~50 m farq — bir xil joy.
        $match = $this->service->findMatch(
            inn: null, phone: null, name: 'Korzinka',
            region: 'Toshkent shahri', district: 'Yunusobod tumani',
            latitude: 41.3504000, longitude: 69.2802000,
        );

        $this->assertNotNull($match);
        $this->assertSame($entry->id, $match->id);
    }

    public function test_find_or_create_creates_new_when_no_match(): void
    {
        $entry = $this->service->findOrCreate([
            'name' => 'Yangi',
            'inn' => '700700700',
            'phone' => '+998 90 700-70-70',
        ], source: 'manual');

        $this->assertTrue($entry->wasRecentlyCreated);
        $this->assertSame('700700700', $entry->inn);
        $this->assertSame('907007070', $entry->phone_normalized);
        $this->assertSame('manual', $entry->source);
    }

    public function test_find_or_create_returns_existing_on_match(): void
    {
        $existing = DirectoryShop::factory()->create(['inn' => '700700700']);

        $entry = $this->service->findOrCreate(['name' => 'Boshqa', 'inn' => '700700700'], source: 'manual');

        $this->assertFalse($entry->wasRecentlyCreated);
        $this->assertSame($existing->id, $entry->id);
    }

    public function test_find_or_create_trims_empty_inn_to_null(): void
    {
        $entry = $this->service->findOrCreate(['name' => 'Bo\'sh INN', 'inn' => '   '], source: 'manual');

        $this->assertNull($entry->inn);
    }
}
