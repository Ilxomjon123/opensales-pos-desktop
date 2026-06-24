<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\NominatimReverseGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NominatimReverseGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('array')->clear();
        config()->set('cache.default', 'array');
    }

    public function test_returns_normalized_uz_address_for_tashkent(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Bunyodkor shoh ko\'chasi',
                    'house_number' => '12',
                    'city_district' => 'Chilonzor tumani',
                    'state' => 'Toshkent shahri',
                    'country' => 'Uzbekistan',
                    'country_code' => 'uz',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(41.3111, 69.2797);

        $this->assertSame('Toshkent shahri', $result['region']);
        $this->assertSame('Chilonzor tumani', $result['district']);
        $this->assertSame('Bunyodkor shoh ko\'chasi, 12', $result['address']);
        $this->assertSame('uz', $result['country_code']);
    }

    public function test_returns_uppercase_country_code_lowercased(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'state' => 'Almaty Region',
                    'country_code' => 'KZ',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(43.2389, 76.8897);

        $this->assertSame('kz', $result['country_code']);
    }

    public function test_skips_mahalla_suburb_and_uses_city_for_regional_cities(): void
    {
        // Nominatim Namangan uchun shu shaklda javob qaytaradi:
        // suburb = "Sardoba mahallasi" (tuman EMAS), city = "Namangan shahri" (haqiqiy tuman)
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'улица Алишера Навои',
                    'suburb' => 'Sardoba mahallasi',
                    'city' => 'Namangan shahri',
                    'state' => 'Namangan Viloyati',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(40.9921, 71.6665);

        $this->assertSame('Namangan viloyati', $result['region']);
        $this->assertSame('Namangan shahri', $result['district']);
        $this->assertSame('улица Алишера Навои', $result['address']);
    }

    public function test_falls_back_to_suburb_when_city_district_missing(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Amir Temur ko\'chasi',
                    'suburb' => 'Yunusobod tumani',
                    'state' => 'Toshkent shahri',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(41.35, 69.28);

        $this->assertSame('Toshkent shahri', $result['region']);
        $this->assertSame('Yunusobod tumani', $result['district']);
        $this->assertSame('Amir Temur ko\'chasi', $result['address']);
    }

    public function test_returns_empty_on_http_error(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('', 500),
        ]);

        $result = $this->geocoder()->reverse(41.3, 69.2);

        $this->assertSame(['region' => null, 'district' => null, 'address' => null, 'country_code' => null], $result);
    }

    public function test_returns_empty_when_address_missing(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'Unable to geocode']),
        ]);

        $result = $this->geocoder()->reverse(0.5, 0.5);

        $this->assertSame(['region' => null, 'district' => null, 'address' => null, 'country_code' => null], $result);
    }

    public function test_handles_tashkent_with_no_state_only_city_and_cyrillic_county(): void
    {
        // Real Nominatim Toshkent shahri uchun shu shaklda javob qaytaradi:
        // state yo'q, city = "Toshkent", county = Cyrillic Russian district
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Махтумкули улица',
                    'neighbourhood' => 'Чулпан махалля',
                    'county' => 'Яшнабадский район',
                    'city' => 'Toshkent',
                    'country_code' => 'uz',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(41.3088, 69.2932);

        $this->assertSame('Toshkent shahri', $result['region']);
        $this->assertSame('Yashnobod tumani', $result['district']);
        $this->assertSame('Махтумкули улица', $result['address']);
        $this->assertSame('uz', $result['country_code']);
    }

    public function test_resolves_cyrillic_uzbek_district_alias(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Buyuk Ipak Yo\'li',
                    'city_district' => 'Миробод тумани',
                    'city' => 'Тошкент',
                    'country_code' => 'uz',
                ],
            ]),
        ]);

        $result = $this->geocoder()->reverse(41.30, 69.29);

        $this->assertSame('Toshkent shahri', $result['region']);
        $this->assertSame('Mirobod tumani', $result['district']);
    }

    public function test_caches_result_to_avoid_duplicate_requests(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => ['state' => 'Toshkent shahri', 'city_district' => 'Mirobod tumani'],
            ]),
        ]);

        $this->geocoder()->reverse(41.3111, 69.2797);
        $this->geocoder()->reverse(41.3111, 69.2797);

        Http::assertSentCount(1);
    }

    private function geocoder(): NominatimReverseGeocoder
    {
        return $this->app->make(NominatimReverseGeocoder::class);
    }
}
