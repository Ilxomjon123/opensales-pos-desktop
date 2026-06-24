<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Contracts\ReverseGeocoderInterface;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ResolveMapLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $dealerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $dealer = Dealer::factory()->create();
        $this->dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        // Reverse geocoder testlarda fake — har doim bo'sh manzil, UZ country code
        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return ['region' => null, 'district' => null, 'address' => null, 'country_code' => 'uz'];
            }
        });
    }

    public function test_resolves_long_google_maps_url_without_network(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://www.google.com/maps?q=41.3111,69.2797',
            ]));

        $response->assertOk()->assertExactJson([
            'lat' => 41.3111,
            'lng' => 69.2797,
            'region' => null,
            'district' => null,
            'address' => null,
            'outside_uz' => false,
            'region_id' => null,
            'district_id' => null,
        ]);
    }

    public function test_resolves_long_yandex_maps_url(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://yandex.uz/maps/?ll=69.2797%2C41.3111&z=15',
            ]));

        $response->assertOk()->assertExactJson([
            'lat' => 41.3111,
            'lng' => 69.2797,
            'region' => null,
            'district' => null,
            'address' => null,
            'outside_uz' => false,
            'region_id' => null,
            'district_id' => null,
        ]);
    }

    public function test_resolves_plain_coordinate_string(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => '41.3111, 69.2797',
            ]));

        $response->assertOk()->assertExactJson([
            'lat' => 41.3111,
            'lng' => 69.2797,
            'region' => null,
            'district' => null,
            'address' => null,
            'outside_uz' => false,
            'region_id' => null,
            'district_id' => null,
        ]);
    }

    public function test_resolves_google_short_url_via_redirect(): void
    {
        // maps.app.goo.gl 302 redirect → /maps/search/lat,+lng?... shakliga olib boradi
        Http::fake([
            'https://maps.app.goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://www.google.com/maps/search/41.001531,+71.672291?entry=tts',
            ]),
            'https://www.google.com/*' => Http::response('<html></html>', 200),
        ]);

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://maps.app.goo.gl/U72pngYGByVViAx16',
            ]));

        $response->assertOk()->assertExactJson([
            'lat' => 41.001531,
            'lng' => 71.672291,
            'region' => null,
            'district' => null,
            'address' => null,
            'outside_uz' => false,
            'region_id' => null,
            'district_id' => null,
        ]);
    }

    public function test_flags_outside_uz_when_country_code_is_not_uz(): void
    {
        Http::preventStrayRequests();

        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return ['region' => null, 'district' => null, 'address' => null, 'country_code' => 'kz'];
            }
        });

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => '43.2389,76.8897',
            ]));

        $response->assertOk()->assertJson(['outside_uz' => true]);
    }

    public function test_uses_bbox_fallback_when_country_code_missing(): void
    {
        Http::preventStrayRequests();

        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return ['region' => null, 'district' => null, 'address' => null, 'country_code' => null];
            }
        });

        // Moskva — UZ bbox dan tashqarida
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', ['url' => '55.7558,37.6173']))
            ->assertOk()
            ->assertJson(['outside_uz' => true]);

        // Toshkent — UZ bbox ichida
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', ['url' => '41.3111,69.2797']))
            ->assertOk()
            ->assertJson(['outside_uz' => false]);
    }

    public function test_returns_404_when_link_has_no_coordinates(): void
    {
        Http::fake([
            'https://maps.app.goo.gl/*' => Http::response('<html></html>', 200),
        ]);

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://maps.app.goo.gl/abc123',
            ]));

        $response->assertStatus(404);
    }

    public function test_rejects_non_map_domains(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://example.com/whatever',
            ]));

        $response->assertStatus(422);
    }

    public function test_requires_authenticated_dealer(): void
    {
        $response = $this->getJson(route('dealer.shops.resolve-map-link', [
            'url' => 'https://www.google.com/maps?q=41.3111,69.2797',
        ]));

        $response->assertStatus(401);
    }

    public function test_returns_422_when_url_is_empty(): void
    {
        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', ['url' => '']));

        $response->assertStatus(422);
    }

    public function test_includes_geocoded_address_in_response(): void
    {
        Http::preventStrayRequests();

        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return [
                    'region' => 'Toshkent shahri',
                    'district' => 'Chilonzor tumani',
                    'address' => 'Bunyodkor shoh ko\'chasi, 12',
                    'country_code' => 'uz',
                ];
            }
        });

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.resolve-map-link', [
                'url' => 'https://www.google.com/maps?q=41.3111,69.2797',
            ]));

        $response->assertOk()->assertExactJson([
            'lat' => 41.3111,
            'lng' => 69.2797,
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
            'address' => 'Bunyodkor shoh ko\'chasi, 12',
            'outside_uz' => false,
            'region_id' => null,
            'district_id' => null,
        ]);
    }
}
