<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Models\Dealer;
use App\Models\DirectoryShop;
use App\Models\Shop;
use App\Services\PublicShopRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DirectoryShopSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_shop_creates_a_directory_entry_and_links_it(): void
    {
        $shop = Shop::factory()->create(['inn' => '123456789']);

        $this->assertNotNull($shop->directory_id);
        $this->assertDatabaseHas('directory_shops', [
            'id' => $shop->directory_id,
            'inn' => '123456789',
            'source' => 'shop_sync',
        ]);
    }

    public function test_two_dealers_with_same_inn_share_one_directory_entry(): void
    {
        $shopA = Shop::factory()->for(Dealer::factory())->create(['inn' => '555555555']);
        $shopB = Shop::factory()->for(Dealer::factory())->create(['inn' => '555555555']);

        $this->assertSame($shopA->fresh()->directory_id, $shopB->fresh()->directory_id);
        $this->assertSame(1, DirectoryShop::query()->where('inn', '555555555')->count());
    }

    public function test_shops_without_inn_dedup_by_phone(): void
    {
        $shopA = Shop::factory()->create([
            'inn' => null,
            'phone' => '+998 90 111-22-33',
        ]);
        $shopB = Shop::factory()->for(Dealer::factory())->create([
            'inn' => null,
            'phone' => '90 111 22 33',
        ]);

        $this->assertSame($shopA->fresh()->directory_id, $shopB->fresh()->directory_id);
        $this->assertSame(1, DirectoryShop::query()->where('phone_normalized', '901112233')->count());
    }

    public function test_different_businesses_create_separate_directory_entries(): void
    {
        Shop::factory()->create(['inn' => '111111111']);
        Shop::factory()->create(['inn' => '222222222']);

        $this->assertSame(2, DirectoryShop::query()->count());
    }

    public function test_shop_without_inn_or_phone_still_creates_directory_entry(): void
    {
        $shop = Shop::factory()->create(['inn' => null, 'phone' => null, 'name' => 'Nomli mijoz']);

        $this->assertNotNull($shop->fresh()->directory_id);
        $this->assertDatabaseHas('directory_shops', ['name' => 'Nomli mijoz', 'inn' => null]);
    }

    public function test_bot_registration_syncs_to_directory(): void
    {
        $dealer = Dealer::factory()->create();

        app(PublicShopRegistrationService::class)->register(
            dealer: $dealer,
            telegramId: 555000111,
            shopName: 'Bot do\'kon',
            address: 'Chilonzor 5',
            latitude: 41.30,
            longitude: 69.24,
            phone: '+998901112233',
        );

        $shop = Shop::query()->where('name', 'Bot do\'kon')->firstOrFail();

        $this->assertNotNull($shop->directory_id);
        $this->assertDatabaseHas('directory_shops', [
            'id' => $shop->directory_id,
            'phone_normalized' => '901112233',
        ]);
    }

    public function test_dedup_holds_when_shop_created_inside_outer_transaction(): void
    {
        // Directory'da INN allaqachon bor. Tashqi DB::transaction ichida shu INN bilan
        // shop yaratilganda observer ishga tushadi — yangi yozuv emas, mavjudga bog'lanadi.
        $existing = DirectoryShop::factory()->create(['inn' => '321321321']);

        $shop = DB::transaction(fn () => Shop::factory()->create(['inn' => '321321321']));

        $this->assertSame($existing->id, $shop->fresh()->directory_id);
        $this->assertSame(1, DirectoryShop::query()->where('inn', '321321321')->count());
    }
}
