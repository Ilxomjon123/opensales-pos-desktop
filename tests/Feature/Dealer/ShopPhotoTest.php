<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ShopPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $dealerUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->dealer = Dealer::factory()->create();
        $this->dealerUser = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_upload_shop_photo(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['photo' => null]);

        $this->actingAs($this->dealerUser)
            ->post(route('dealer.shops.photo.update', $shop), [
                'photo' => UploadedFile::fake()->image('shop.jpg'),
            ])
            ->assertRedirect();

        $shop->refresh();
        $this->assertNotNull($shop->photo);
        Storage::disk('public')->assertExists($shop->photo);
    }

    public function test_dealer_can_replace_shop_photo_even_with_members(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create([
            'photo' => UploadedFile::fake()->image('old.jpg')->store('shops', 'public'),
        ]);
        ShopMember::factory()->for($shop)->create();

        $oldPath = $shop->photo;

        $this->actingAs($this->dealerUser)
            ->post(route('dealer.shops.photo.update', $shop), [
                'photo' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect();

        $shop->refresh();
        $this->assertNotNull($shop->photo);
        $this->assertNotSame($oldPath, $shop->photo);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($shop->photo);
    }

    public function test_dealer_can_delete_shop_photo(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create([
            'photo' => UploadedFile::fake()->image('x.jpg')->store('shops', 'public'),
        ]);
        $path = $shop->photo;

        $this->actingAs($this->dealerUser)
            ->delete(route('dealer.shops.photo.destroy', $shop))
            ->assertRedirect();

        $shop->refresh();
        $this->assertNull($shop->photo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_other_dealer_cannot_update_photo(): void
    {
        $otherDealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($otherDealer)->create(['photo' => null]);

        $this->actingAs($this->dealerUser)
            ->post(route('dealer.shops.photo.update', $shop), [
                'photo' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_photo_must_be_image(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['photo' => null]);

        $this->actingAs($this->dealerUser)
            ->post(route('dealer.shops.photo.update', $shop), [
                'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_deliveryman_can_update_photo_of_their_shop(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        $shop = Shop::factory()->for($this->dealer)->create([
            'deliveryman_id' => $deliveryman->id,
            'photo' => null,
        ]);

        $this->actingAs($deliveryman)
            ->post(route('dealer.shops.photo.update', $shop), [
                'photo' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertRedirect();

        $this->assertNotNull($shop->fresh()->photo);
    }

    public function test_index_filters_by_region_and_district(): void
    {
        Shop::factory()->for($this->dealer)->create(['region' => 'Buxoro viloyati', 'district' => 'Buxoro shahri']);
        Shop::factory()->for($this->dealer)->create(['region' => 'Buxoro viloyati', 'district' => 'Kogon tumani']);
        Shop::factory()->for($this->dealer)->create(['region' => 'Samarqand viloyati', 'district' => 'Samarqand shahri']);

        $response = $this->actingAs($this->dealerUser)
            ->get(route('dealer.shops.index', ['region' => 'Buxoro viloyati']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Dealer/Shops/Index')
            ->where('shops.meta.total', 2)
        );

        $this->actingAs($this->dealerUser)
            ->get(route('dealer.shops.index', ['region' => 'Buxoro viloyati', 'district' => 'Kogon tumani']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('shops.meta.total', 1));
    }
}
