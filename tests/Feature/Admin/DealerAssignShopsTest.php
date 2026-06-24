<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\DirectoryShop;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DealerAssignShopsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
    }

    public function test_assign_creates_shops_for_dealer_from_directory(): void
    {
        $dealer = Dealer::factory()->create();
        $entries = DirectoryShop::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.dealers.assign-shops', $dealer), [
                'directory_ids' => $entries->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJson(['created' => 2]);

        foreach ($entries as $entry) {
            $this->assertDatabaseHas('shops', [
                'dealer_id' => $dealer->id,
                'directory_id' => $entry->id,
                'name' => $entry->name,
            ]);
        }
    }

    public function test_assign_skips_already_linked_directory_entry(): void
    {
        $dealer = Dealer::factory()->create();
        $entry = DirectoryShop::factory()->create(['inn' => '111222333']);

        // INN bir xil — ShopObserver shop'ni xuddi shu spravochnik yozuviga qaytadan bog'laydi.
        Shop::factory()->for($dealer)->create(['directory_id' => $entry->id, 'inn' => $entry->inn]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.dealers.assign-shops', $dealer), [
                'directory_ids' => [$entry->id],
            ])
            ->assertOk()
            ->assertJson(['created' => 0]);

        $this->assertSame(
            1,
            Shop::query()->where('dealer_id', $dealer->id)->where('directory_id', $entry->id)->count(),
        );
    }

    public function test_directory_search_excludes_already_owned_entries(): void
    {
        $dealer = Dealer::factory()->create();
        $owned = DirectoryShop::factory()->create(['name' => 'Owned Market', 'inn' => '444555666']);
        $free = DirectoryShop::factory()->create(['name' => 'Free Market', 'inn' => '777888999']);

        Shop::factory()->for($dealer)->create(['directory_id' => $owned->id, 'inn' => $owned->inn]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.dealers.directory-search', $dealer).'?q=Market');

        $response->assertOk();

        $ids = collect($response->json('shops'))->pluck('id')->all();

        $this->assertNotContains($owned->id, $ids);
        $this->assertContains($free->id, $ids);
    }

    public function test_directory_search_filters_by_region_and_district(): void
    {
        $dealer = Dealer::factory()->create();
        $match = DirectoryShop::factory()->create([
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
            'inn' => '101010101',
        ]);
        $otherDistrict = DirectoryShop::factory()->create([
            'region' => 'Toshkent shahri',
            'district' => 'Yunusobod tumani',
            'inn' => '202020202',
        ]);
        $otherRegion = DirectoryShop::factory()->create([
            'region' => 'Andijon viloyati',
            'district' => 'Asaka tumani',
            'inn' => '303030303',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.dealers.directory-search', $dealer).'?region='.urlencode('Toshkent shahri').'&district='.urlencode('Chilonzor tumani'));

        $response->assertOk();

        $ids = collect($response->json('shops'))->pluck('id')->all();

        $this->assertContains($match->id, $ids);
        $this->assertNotContains($otherDistrict->id, $ids);
        $this->assertNotContains($otherRegion->id, $ids);
    }

    public function test_directory_search_paginates_with_offset(): void
    {
        $dealer = Dealer::factory()->create();
        DirectoryShop::factory()->count(150)->create();

        $first = $this->actingAs($this->admin)
            ->getJson(route('admin.dealers.directory-search', $dealer));
        $first->assertOk()->assertJson(['has_more' => true]);
        $this->assertCount(100, $first->json('shops'));

        $second = $this->actingAs($this->admin)
            ->getJson(route('admin.dealers.directory-search', $dealer).'?offset=100');
        $second->assertOk()->assertJson(['has_more' => false]);
        $this->assertCount(50, $second->json('shops'));

        // Sahifalar bir-birini takrorlamaydi
        $firstIds = collect($first->json('shops'))->pluck('id');
        $secondIds = collect($second->json('shops'))->pluck('id');
        $this->assertEmpty($firstIds->intersect($secondIds));
    }

    public function test_non_admin_cannot_assign(): void
    {
        $dealer = Dealer::factory()->create();
        $entry = DirectoryShop::factory()->create();
        $dealerUser = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);

        // super_admin middleware'i admin bo'lmaganlarni redirect qiladi (403 emas).
        $this->actingAs($dealerUser)
            ->post(route('admin.dealers.assign-shops', $dealer), [
                'directory_ids' => [$entry->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('shops', [
            'dealer_id' => $dealer->id,
            'directory_id' => $entry->id,
        ]);
    }
}
