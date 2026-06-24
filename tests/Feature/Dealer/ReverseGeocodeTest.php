<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Contracts\ReverseGeocoderInterface;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReverseGeocodeTest extends TestCase
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
    }

    public function test_returns_address_for_valid_coordinates(): void
    {
        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode', [
                'lat' => 41.3111,
                'lng' => 69.2797,
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

    public function test_marks_outside_uz_when_country_differs(): void
    {
        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return ['region' => null, 'district' => null, 'address' => null, 'country_code' => 'kz'];
            }
        });

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode', [
                'lat' => 43.2389,
                'lng' => 76.8897,
            ]));

        $response->assertOk()->assertJson(['outside_uz' => true]);
    }

    public function test_returns_422_for_non_numeric_coordinates(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode', ['lat' => 'abc', 'lng' => 69.2797]))
            ->assertStatus(422);
    }

    public function test_returns_422_for_missing_coordinates(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode'))
            ->assertStatus(422);
    }

    public function test_returns_422_for_out_of_range_coordinates(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode', ['lat' => 95, 'lng' => 69.2797]))
            ->assertStatus(422);

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.reverse-geocode', ['lat' => 41.3111, 'lng' => 200]))
            ->assertStatus(422);
    }

    public function test_requires_authenticated_dealer(): void
    {
        $this->getJson(route('dealer.shops.reverse-geocode', ['lat' => 41.3111, 'lng' => 69.2797]))
            ->assertStatus(401);
    }
}
