<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    private function assertFinds(string $query, int $count): void
    {
        $this->actingAs($this->user)
            ->get(route('dealer.shops.index', ['search' => $query]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Shops/Index')
                ->has('shops.data', $count)
            );
    }

    public function test_search_is_case_insensitive(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'Ishonch market']);

        $this->assertFinds('ishonch', 1);
        $this->assertFinds('ISHONCH', 1);
        $this->assertFinds('IsHoN', 1);
    }

    public function test_search_matches_cyrillic_query_against_latin_name(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'Ishonch market']);

        $this->assertFinds('ишонч', 1);
        $this->assertFinds('ИШОН', 1);
    }

    public function test_search_matches_latin_query_against_cyrillic_name(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'ишонч маркет']);

        $this->assertFinds('ishonch', 1);
    }

    public function test_search_excludes_non_matching(): void
    {
        Shop::factory()->for($this->dealer)->create(['name' => 'Ishonch market']);

        $this->assertFinds('boshqa', 0);
    }
}
