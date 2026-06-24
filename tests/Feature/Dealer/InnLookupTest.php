<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Contracts\InnLookupServiceInterface;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InnLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $dealerUser;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dealer = Dealer::factory()->create();
        $this->dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_returns_local_shops_when_inn_matches_in_database(): void
    {
        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                throw new \RuntimeException('OrgInfo must not be called when local match exists');
            }
        });

        Shop::factory()->for($this->dealer)->create([
            'inn' => '123456789',
            'name' => 'Local shop',
        ]);

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.inn-lookup', ['inn' => '123456789']));

        $response->assertOk()->assertJsonPath('shops.0.name', 'Local shop')
            ->assertJsonPath('shops.0.is_own', true);
    }

    public function test_falls_back_to_orginfo_when_no_local_match(): void
    {
        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                return [
                    'inn' => $inn,
                    'name' => 'OrgInfo Inc',
                    'legal_name' => 'OOO OrgInfo',
                    'region' => 'Toshkent shahri',
                    'district' => 'Chilonzor tumani',
                    'address' => 'Test ko\'chasi 1',
                ];
            }
        });

        $response = $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.inn-lookup', ['inn' => '987654321']));

        $response->assertOk()
            ->assertJsonMissingPath('shops')
            ->assertJsonPath('name', 'OrgInfo Inc')
            ->assertJsonPath('legal_name', 'OOO OrgInfo');
    }

    public function test_returns_503_when_orginfo_unavailable_and_no_local_match(): void
    {
        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                return null;
            }
        });

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.inn-lookup', ['inn' => '987654321']))
            ->assertStatus(503);
    }

    public function test_route_rejects_non_9_digit_inn(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson('/dealer/shops-api/inn-lookup/12')
            ->assertStatus(404);
    }

    public function test_marks_other_dealer_shop_as_not_own(): void
    {
        $otherDealer = Dealer::factory()->create();
        Shop::factory()->for($otherDealer)->create([
            'inn' => '555555555',
            'name' => 'Other dealer shop',
        ]);

        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                throw new \RuntimeException('Should not be called');
            }
        });

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.inn-lookup', ['inn' => '555555555']))
            ->assertOk()
            ->assertJsonPath('shops.0.is_own', false);
    }
}
