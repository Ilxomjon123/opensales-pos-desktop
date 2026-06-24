<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopBranchTest extends TestCase
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

    public function test_main_branch_total_balance_sums_branches(): void
    {
        $main = Shop::factory()->for($this->dealer)->create(['balance' => 100_000]);
        Shop::factory()->for($this->dealer)->create([
            'parent_shop_id' => $main->id,
            'balance' => -50_000,
        ]);
        Shop::factory()->for($this->dealer)->create([
            'parent_shop_id' => $main->id,
            'balance' => 30_000,
        ]);

        $this->assertSame(80_000, $main->fresh()->totalBalanceWithBranches());
    }

    public function test_branch_total_balance_returns_own_only(): void
    {
        $main = Shop::factory()->for($this->dealer)->create(['balance' => 500_000]);
        $branch = Shop::factory()->for($this->dealer)->create([
            'parent_shop_id' => $main->id,
            'balance' => 25_000,
        ]);

        $this->assertSame(25_000, $branch->fresh()->totalBalanceWithBranches());
        $this->assertFalse($branch->fresh()->isMainBranch());
        $this->assertTrue($main->fresh()->isMainBranch());
    }

    public function test_dealer_can_create_branch_with_parent(): void
    {
        $parent = Shop::factory()->for($this->dealer)->create();

        $payload = [
            'name' => 'Filial A',
            'phone' => '+998901234567',
            'latitude' => 41.3,
            'longitude' => 69.2,
            'parent_shop_id' => $parent->id,
        ];

        $this->actingAs($this->user)
            ->post(route('dealer.shops.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('shops', [
            'dealer_id' => $this->dealer->id,
            'parent_shop_id' => $parent->id,
            'name' => 'Filial A',
        ]);
    }

    public function test_cannot_attach_to_parent_from_other_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreignParent = Shop::factory()->for($otherDealer)->create();

        $payload = [
            'name' => 'Filial X',
            'phone' => '+998901234567',
            'latitude' => 41.3,
            'longitude' => 69.2,
            'parent_shop_id' => $foreignParent->id,
        ];

        $this->actingAs($this->user)
            ->post(route('dealer.shops.store'), $payload)
            ->assertSessionHasErrors('parent_shop_id');
    }

    public function test_cannot_attach_to_a_branch_as_parent(): void
    {
        $main = Shop::factory()->for($this->dealer)->create();
        $existingBranch = Shop::factory()->for($this->dealer)->create([
            'parent_shop_id' => $main->id,
        ]);

        $payload = [
            'name' => 'Sub-filial',
            'phone' => '+998901234567',
            'latitude' => 41.3,
            'longitude' => 69.2,
            'parent_shop_id' => $existingBranch->id,
        ];

        $this->actingAs($this->user)
            ->post(route('dealer.shops.store'), $payload)
            ->assertSessionHasErrors('parent_shop_id');
    }

    public function test_cannot_set_self_as_parent_on_update(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();

        $payload = [
            'name' => $shop->name,
            'phone' => $shop->phone ?? '+998901234567',
            'latitude' => 41.3,
            'longitude' => 69.2,
            'parent_shop_id' => $shop->id,
        ];

        $this->actingAs($this->user)
            ->put(route('dealer.shops.update', $shop), $payload)
            ->assertSessionHasErrors('parent_shop_id');
    }

    public function test_cannot_make_shop_with_branches_into_a_branch(): void
    {
        $main = Shop::factory()->for($this->dealer)->create();
        Shop::factory()->for($this->dealer)->create(['parent_shop_id' => $main->id]);

        $newParent = Shop::factory()->for($this->dealer)->create();

        $payload = [
            'name' => $main->name,
            'phone' => $main->phone ?? '+998901234567',
            'latitude' => 41.3,
            'longitude' => 69.2,
            'parent_shop_id' => $newParent->id,
        ];

        $this->actingAs($this->user)
            ->put(route('dealer.shops.update', $main), $payload)
            ->assertSessionHasErrors('parent_shop_id');
    }

    public function test_balance_page_shows_aggregated_total_for_main_branch(): void
    {
        $main = Shop::factory()->for($this->dealer)->create(['name' => 'Bosh']);
        $branch = Shop::factory()->for($this->dealer)->create([
            'parent_shop_id' => $main->id,
            'name' => 'Filial',
        ]);

        Payment::factory()->for($this->dealer)->for($main)->credit()->create(['amount' => 100_000]);
        Payment::factory()->for($this->dealer)->for($branch)->debit()->create(['amount' => 30_000]);

        $this->actingAs($this->user)
            ->get(route('dealer.shops-balance.index'))
            ->assertInertia(fn ($page) => $page
                ->where('shops.data', function ($rows) {
                    $bosh = collect($rows)->firstWhere('name', 'Bosh');
                    $filial = collect($rows)->firstWhere('name', 'Filial');

                    return $bosh['balance'] === 100_000
                        && $bosh['branches_balance_sum'] === -30_000
                        && $bosh['total_balance_with_branches'] === 70_000
                        && $bosh['is_main_branch'] === true
                        && $filial['balance'] === -30_000
                        && $filial['is_main_branch'] === false
                        && $filial['parent_shop_id'] !== null
                        && $filial['parent_name'] === 'Bosh';
                })
            );
    }
}
