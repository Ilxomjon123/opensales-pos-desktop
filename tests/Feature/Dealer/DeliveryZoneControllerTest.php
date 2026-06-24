<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UzGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeliveryZoneControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $dealerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Hudud ro'yxati va validatsiya DB geo katalogiga tayanadi.
        $this->seed(CountrySeeder::class);
        $this->seed(UzGeoSeeder::class);

        $this->dealer = Dealer::factory()->create();
        $this->dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_view_delivery_zones_page(): void
    {
        $this->actingAs($this->dealerUser)
            ->get(route('dealer.settings.delivery-zones.show'))
            ->assertOk();
    }

    public function test_update_syncs_whole_region_and_districts(): void
    {
        $this->actingAs($this->dealerUser)
            ->put(route('dealer.settings.delivery-zones.update'), [
                'zones' => [
                    ['region' => 'Toshkent shahri', 'whole_region' => true, 'districts' => []],
                    ['region' => 'Andijon viloyati', 'whole_region' => false, 'districts' => ['Asaka tumani', 'Andijon shahri']],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dealer_delivery_zones', [
            'dealer_id' => $this->dealer->id,
            'region' => 'Toshkent shahri',
            'district' => null,
        ]);
        $this->assertDatabaseHas('dealer_delivery_zones', [
            'dealer_id' => $this->dealer->id,
            'region' => 'Andijon viloyati',
            'district' => 'Asaka tumani',
        ]);
        $this->assertSame(3, $this->dealer->deliveryZones()->count());
    }

    public function test_update_replaces_existing_zones(): void
    {
        $this->dealer->deliveryZones()->create(['region' => 'Buxoro viloyati', 'district' => null]);

        $this->actingAs($this->dealerUser)
            ->put(route('dealer.settings.delivery-zones.update'), [
                'zones' => [
                    ['region' => 'Toshkent shahri', 'whole_region' => true, 'districts' => []],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('dealer_delivery_zones', [
            'dealer_id' => $this->dealer->id,
            'region' => 'Buxoro viloyati',
        ]);
        $this->assertSame(1, $this->dealer->deliveryZones()->count());
    }

    public function test_empty_zones_clears_all(): void
    {
        $this->dealer->deliveryZones()->create(['region' => 'Buxoro viloyati', 'district' => null]);

        $this->actingAs($this->dealerUser)
            ->put(route('dealer.settings.delivery-zones.update'), ['zones' => []])
            ->assertRedirect();

        $this->assertSame(0, $this->dealer->deliveryZones()->count());
    }

    public function test_rejects_unknown_region(): void
    {
        $this->actingAs($this->dealerUser)
            ->put(route('dealer.settings.delivery-zones.update'), [
                'zones' => [
                    ['region' => 'Mars viloyati', 'whole_region' => true, 'districts' => []],
                ],
            ])
            ->assertSessionHasErrors('zones.0.region');
    }

    public function test_rejects_district_not_in_region(): void
    {
        $this->actingAs($this->dealerUser)
            ->put(route('dealer.settings.delivery-zones.update'), [
                'zones' => [
                    ['region' => 'Toshkent shahri', 'whole_region' => false, 'districts' => ['Asaka tumani']],
                ],
            ])
            ->assertSessionHasErrors('zones.0.districts.0');
    }
}
