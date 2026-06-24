<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopPhoneLookupTest extends TestCase
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

    public function test_returns_directory_entry_and_marks_own(): void
    {
        // O'z shopi → directory yozuvi yaratiladi va bog'lanadi.
        Shop::factory()->for($this->dealer)->create([
            'phone' => '+998 90 123-45-67',
            'name' => 'Mening do\'konim',
        ]);

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.phone-lookup', ['phone' => '901234567']))
            ->assertOk()
            ->assertJsonPath('shops.0.name', 'Mening do\'konim')
            ->assertJsonPath('shops.0.is_own', true);
    }

    public function test_marks_other_dealer_entry_as_not_own(): void
    {
        $otherDealer = Dealer::factory()->create();
        Shop::factory()->for($otherDealer)->create([
            'phone' => '+998 90 555-66-77',
            'name' => 'Boshqa diller do\'koni',
        ]);

        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.phone-lookup', ['phone' => '905556677']))
            ->assertOk()
            ->assertJsonPath('shops.0.is_own', false);
    }

    public function test_returns_404_when_no_match(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.phone-lookup', ['phone' => '998901234567']))
            ->assertStatus(404);
    }

    public function test_rejects_short_phone(): void
    {
        $this->actingAs($this->dealerUser)
            ->getJson(route('dealer.shops.phone-lookup', ['phone' => '123']))
            ->assertStatus(422);
    }
}
