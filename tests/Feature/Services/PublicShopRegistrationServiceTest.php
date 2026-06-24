<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\ReverseGeocoderInterface;
use App\Exceptions\Domain\OutsideDeliveryZoneException;
use App\Models\Dealer;
use App\Services\PublicShopRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicShopRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeocoder(?string $region, ?string $district): void
    {
        $this->app->instance(ReverseGeocoderInterface::class, new class($region, $district) implements ReverseGeocoderInterface
        {
            public function __construct(private ?string $region, private ?string $district) {}

            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return [
                    'region' => $this->region,
                    'district' => $this->district,
                    'address' => 'Test manzil',
                    'country_code' => 'uz',
                ];
            }
        });
    }

    public function test_registers_and_fills_region_from_coordinates(): void
    {
        $this->fakeGeocoder('Toshkent shahri', 'Chilonzor tumani');

        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $member = app(PublicShopRegistrationService::class)->register(
            dealer: $dealer,
            telegramId: 555,
            shopName: 'Test Do\'kon',
            address: 'Joylashuv: 41.31, 69.27',
            latitude: 41.31,
            longitude: 69.27,
        );

        $this->assertSame('Toshkent shahri', $member->shop->region);
        $this->assertSame('Chilonzor tumani', $member->shop->district);
    }

    public function test_blocks_registration_outside_zone(): void
    {
        $this->fakeGeocoder('Samarqand viloyati', 'Urgut tumani');

        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $this->expectException(OutsideDeliveryZoneException::class);

        app(PublicShopRegistrationService::class)->register(
            dealer: $dealer,
            telegramId: 556,
            shopName: 'Tashqari Do\'kon',
            address: 'Joylashuv: 39.65, 67.0',
            latitude: 39.65,
            longitude: 67.0,
        );

        $this->assertDatabaseEmpty('shops');
    }

    public function test_text_address_without_coordinates_is_allowed(): void
    {
        $dealer = Dealer::factory()->create();
        $dealer->deliveryZones()->create(['region' => 'Toshkent shahri', 'district' => 'Chilonzor tumani']);

        $member = app(PublicShopRegistrationService::class)->register(
            dealer: $dealer,
            telegramId: 557,
            shopName: 'Matn Do\'kon',
            address: 'Samarqand, Registon ko\'chasi 1',
        );

        $this->assertNull($member->shop->region);
        $this->assertDatabaseHas('shops', ['id' => $member->shop->id]);
    }
}
