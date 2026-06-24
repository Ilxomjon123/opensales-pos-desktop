<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Services\Geo\RegionMatcher;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RuGeoSeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegionMatcherTest extends TestCase
{
    use RefreshDatabase;

    private Country $uz;

    private RegionMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);

        $this->uz = Country::query()->where('code', 'uz')->firstOrFail();
        $this->matcher = new RegionMatcher;
    }

    public function test_matches_district_with_modifier_letter_apostrophe(): void
    {
        $result = $this->matcher->match(
            $this->uz,
            'Namangan Viloyati',
            "To\u{02BB}raqo\u{02BB}rg\u{02BB}on tumani",
        );

        $this->assertSame('Namangan viloyati', $result['region']?->name);
        $this->assertSame("To'raqo'rg'on tumani", $result['district']?->name);
    }

    public function test_matches_district_with_ascii_apostrophe(): void
    {
        $result = $this->matcher->match($this->uz, 'Namangan viloyati', "To'raqo'rg'on tumani");

        $this->assertSame('Namangan viloyati', $result['region']?->name);
        $this->assertSame("To'raqo'rg'on tumani", $result['district']?->name);
    }

    public function test_matches_russian_alias_to_canonical(): void
    {
        $result = $this->matcher->match($this->uz, 'Ташкентская область', null);

        $this->assertSame('Toshkent viloyati', $result['region']?->name);
    }

    public function test_infers_region_from_district_alias(): void
    {
        // Region berilmagan, faqat Toshkent shahri tumani (ruscha).
        $result = $this->matcher->match($this->uz, null, 'Чиланзарский район');

        $this->assertSame('Toshkent shahri', $result['region']?->name);
        $this->assertSame('Chilonzor tumani', $result['district']?->name);
    }

    public function test_returns_null_for_both_null(): void
    {
        $result = $this->matcher->match($this->uz, null, null);

        $this->assertNull($result['region']);
        $this->assertNull($result['district']);
    }

    public function test_matches_within_country_scope(): void
    {
        $this->seed(RuGeoSeeder::class);
        $ru = Country::query()->where('code', 'ru')->firstOrFail();

        $result = $this->matcher->match($ru, 'Москва', null);

        $this->assertSame('Москва', $result['region']?->name);
        $this->assertSame($ru->id, $result['region']?->country_id);
    }
}
