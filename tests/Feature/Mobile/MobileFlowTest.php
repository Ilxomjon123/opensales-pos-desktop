<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\BotVisibility;
use App\Enums\OrderChannel;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvite;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\MobileAppLinkService;
use App\Services\ShopInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MobileFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_and_verify_otp_creates_customer_with_token(): void
    {
        $this->postJson('/api/mobile/auth/request-otp', ['phone' => '+998 90 111 22 33'])
            ->assertOk();

        // Test muhitida kod barqaror: 000000
        $response = $this->postJson('/api/mobile/auth/verify-otp', [
            'phone' => '+998 90 111 22 33',
            'code' => '000000',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'customer' => ['id', 'phone'], 'has_membership']);

        $this->assertDatabaseHas('customers', ['phone' => '+998901112233']);
    }

    public function test_reviewer_test_phone_logs_in_with_fixed_code_and_no_sms(): void
    {
        config([
            'services.sms.review_phone' => '+998 90 000 00 00',
            'services.sms.review_code' => '654321',
        ]);

        // request-otp: reviewer raqami uchun erta qaytadi — kod cache'ga
        // yozilmaydi va SMS yuborilmaydi (send shu nuqtadan keyin chaqiriladi).
        $this->postJson('/api/mobile/auth/request-otp', ['phone' => '+998900000000'])
            ->assertOk();
        $this->assertFalse(Cache::has('mobile_otp:code:+998900000000'));
        $this->assertFalse(Cache::has('mobile_otp:cooldown:+998900000000'));

        // verify-otp: prod'da ham fixed kod bilan kirish ishlaydi.
        $this->postJson('/api/mobile/auth/verify-otp', [
            'phone' => '+998900000000',
            'code' => '654321',
        ])->assertCreated()->assertJsonStructure(['token', 'customer' => ['id', 'phone']]);

        // Noto'g'ri kod — rad etiladi.
        $this->postJson('/api/mobile/auth/verify-otp', [
            'phone' => '+998900000000',
            'code' => '111111',
        ])->assertStatus(422);
    }

    public function test_reviewer_login_disabled_when_env_not_set(): void
    {
        // Konfiguratsiya bo'sh (default) — reviewer bypass butunlay o'chiq:
        // ixtiyoriy kod (000000 dan boshqa) qabul qilinmaydi.
        config(['services.sms.review_phone' => null, 'services.sms.review_code' => null]);

        $this->postJson('/api/mobile/auth/verify-otp', [
            'phone' => '+998900000000',
            'code' => '654321',
        ])->assertStatus(422);
    }

    public function test_customer_redeems_invite_then_places_mobile_order(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create(['dealer_id' => $dealer->id, 'role' => UserRole::DEALER]);
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);
        $product = Product::factory()->for($dealer)->create(['price' => 50_000, 'stock' => 20]);

        $invite = app(ShopInviteService::class)->createForShop($shop, $owner);

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        // Scan: invite redeem
        $this->postJson('/api/mobile/auth/redeem-invite', ['token' => $invite->token])
            ->assertCreated();

        $this->assertDatabaseHas('shop_members', [
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
        ]);

        // Savatga qo'shish
        $this->postJson("/api/mobile/dealers/{$dealer->id}/cart/add", [
            'product_id' => $product->id,
            'qty' => 2,
        ], ['X-Shop-Id' => (string) $shop->id])->assertOk();

        // Buyurtma tasdiqlash → channel = mobile_app
        $this->postJson("/api/mobile/dealers/{$dealer->id}/cart/confirm", [], [
            'X-Shop-Id' => (string) $shop->id,
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'channel' => OrderChannel::MOBILE_APP->value,
            'total' => 100_000,
        ]);

        // Buyurtmalar ro'yxati Customer bilan 500 bermasligi kerak (OrderResource can_* guard).
        $this->getJson("/api/mobile/dealers/{$dealer->id}/orders", [
            'X-Shop-Id' => (string) $shop->id,
        ])->assertOk();
    }

    public function test_qr_login_passwordless_logs_in_by_shop_phone_and_connects(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create(['dealer_id' => $dealer->id, 'role' => UserRole::DEALER]);
        $shop = Shop::factory()->for($dealer)->create(['phone' => '+998 90 222 33 44']);

        $invite = app(ShopInviteService::class)->createForShop($shop, $owner);

        $this->postJson('/api/mobile/auth/qr-login', ['token' => $invite->token])
            ->assertCreated()
            ->assertJsonStructure(['token', 'shop' => ['id', 'dealer' => ['id']], 'has_membership'])
            ->assertJsonPath('shop.id', $shop->id)
            ->assertJsonPath('shop.dealer.id', $dealer->id);

        // Shop telefoni bo'yicha customer yaratildi va shu shopga ulandi.
        $customer = Customer::query()->where('phone', '+998902223344')->firstOrFail();
        $this->assertDatabaseHas('shop_members', [
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);
    }

    public function test_qr_login_consumes_invite_one_time_and_rotates(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create(['dealer_id' => $dealer->id, 'role' => UserRole::DEALER]);
        $shop = Shop::factory()->for($dealer)->create(['phone' => '+998905556677']);
        $invite = app(ShopInviteService::class)->createForShop($shop, $owner);

        $this->postJson('/api/mobile/auth/qr-login', ['token' => $invite->token])->assertCreated();

        // inv_ bir martalik: ishlatilgan deb belgilanadi.
        $this->assertNotNull($invite->fresh()->used_at);

        // Ikkinchi marta o'sha token — rad etiladi (allaqachon ishlatilgan).
        $this->postJson('/api/mobile/auth/qr-login', ['token' => $invite->token])->assertStatus(422);

        // Shop uchun yangi (ishlatilmagan) invite rotate qilingan.
        $this->assertSame(1, ShopInvite::query()
            ->where('shop_id', $shop->id)
            ->whereNull('used_at')
            ->count());

        // Bitta member — takror kirish dublikat yaratmaydi.
        $this->assertSame(1, ShopMember::query()->where('shop_id', $shop->id)->count());
    }

    public function test_qr_login_rejects_invalid_or_expired_token(): void
    {
        $this->postJson('/api/mobile/auth/qr-login', ['token' => 'inv_nope'])
            ->assertStatus(422);

        $dealer = Dealer::factory()->create();
        $owner = User::factory()->create(['dealer_id' => $dealer->id, 'role' => UserRole::DEALER]);
        $shop = Shop::factory()->for($dealer)->create(['phone' => '+998901110000']);
        $invite = app(ShopInviteService::class)->createForShop($shop, $owner);
        $invite->forceFill(['expires_at' => now()->subHour()])->save();

        $this->postJson('/api/mobile/auth/qr-login', ['token' => $invite->token])
            ->assertStatus(422);
    }

    public function test_link_telegram_merges_bot_memberships_into_customer(): void
    {
        $dealer = Dealer::factory()->create();
        $shopA = Shop::factory()->for($dealer)->create();
        $shopB = Shop::factory()->for($dealer)->create();

        // Backfill: bot vakili + telefonsiz customer
        $backfill = Customer::factory()->create(['phone' => null]);
        ShopMember::factory()->create(['shop_id' => $shopA->id, 'telegram_id' => 555, 'customer_id' => $backfill->id]);
        ShopMember::factory()->create(['shop_id' => $shopB->id, 'telegram_id' => 555, 'customer_id' => $backfill->id]);

        $customer = Customer::factory()->create(['phone' => '+998901234567']);
        $code = app(MobileAppLinkService::class)->issueCode(555);

        Sanctum::actingAs($customer);
        $this->postJson('/api/mobile/auth/link-telegram', ['code' => $code])->assertOk();

        $this->assertDatabaseHas('shop_members', ['shop_id' => $shopA->id, 'telegram_id' => 555, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('shop_members', ['shop_id' => $shopB->id, 'telegram_id' => 555, 'customer_id' => $customer->id]);
        $this->assertDatabaseMissing('customers', ['id' => $backfill->id]);
    }

    public function test_link_telegram_dedupes_existing_membership_in_same_shop(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $customer = Customer::factory()->create(['phone' => '+998905554433']);
        // Customer allaqachon shu do'konda vakil (mobil-only)
        ShopMember::factory()->create(['shop_id' => $shop->id, 'telegram_id' => null, 'customer_id' => $customer->id]);

        // O'sha do'konda bot vakili (boshqa, backfill customer)
        $backfill = Customer::factory()->create(['phone' => null]);
        ShopMember::factory()->create(['shop_id' => $shop->id, 'telegram_id' => 888, 'customer_id' => $backfill->id]);

        $code = app(MobileAppLinkService::class)->issueCode(888);
        Sanctum::actingAs($customer);
        $this->postJson('/api/mobile/auth/link-telegram', ['code' => $code])->assertOk();

        // Bitta yozuvga birlashishi kerak: telegram_id + customer_id bitta qatorda
        $this->assertSame(1, ShopMember::query()
            ->where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->count());
        $this->assertDatabaseHas('shop_members', [
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'telegram_id' => 888,
        ]);
    }

    public function test_self_registration_fans_out_to_public_dealers(): void
    {
        $public = Dealer::factory()->create(['visibility' => BotVisibility::PUBLIC]);
        $private = Dealer::factory()->create(['visibility' => BotVisibility::PRIVATE]);

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/mobile/auth/register', [
            'shop_name' => 'Test Do\'kon',
            'address' => 'Toshkent sh., Chilonzor',
        ])->assertCreated()->assertJsonPath('joined', 1);

        $this->assertDatabaseHas('shop_members', ['customer_id' => $customer->id]);
        $this->assertSame(
            1,
            ShopMember::query()->where('customer_id', $customer->id)->count(),
        );
        $this->assertSame($public->id, Shop::query()->first()->dealer_id);
        $this->assertDatabaseMissing('shops', ['dealer_id' => $private->id]);
    }

    public function test_discover_empty_when_customer_has_no_address(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/mobile/discovery/dealers')
            ->assertOk()
            ->assertJsonCount(0, 'dealers');
    }

    public function test_discover_lists_public_dealers_covering_area_excludes_closed(): void
    {
        // Mijozning manzili (biror dillerda).
        $home = Dealer::factory()->create();
        $shop = Shop::factory()->for($home)->create(['region' => 'Toshkent', 'district' => 'Chilonzor']);
        $customer = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
        Sanctum::actingAs($customer);

        $open = Dealer::factory()->create(['visibility' => BotVisibility::PUBLIC]);   // zonasiz → hammaga
        $closed = Dealer::factory()->create(['visibility' => BotVisibility::PRIVATE]); // yopiq

        $res = $this->postJson('/api/mobile/discovery/dealers')->assertOk();
        $ids = collect($res->json('dealers'))->pluck('id');

        $this->assertTrue($ids->contains($open->id));
        $this->assertFalse($ids->contains($closed->id));
        $this->assertFalse($ids->contains($home->id)); // allaqachon ulangan
    }

    public function test_join_dealer_uses_selected_joy_and_is_idempotent(): void
    {
        // Mijozning mavjud joyi (boshqa dillerda).
        $other = Dealer::factory()->create();
        $joyShop = Shop::factory()->for($other)->create([
            'name' => 'Anhor do\'kon',
            'address' => 'Toshkent sh., Yunusobod',
        ]);
        $target = Dealer::factory()->create(['visibility' => BotVisibility::PUBLIC]);

        $customer = Customer::factory()->create();
        ShopMember::factory()->create(['shop_id' => $joyShop->id, 'customer_id' => $customer->id]);
        Sanctum::actingAs($customer);

        // Tanlangan joy bo'yicha ulanish — yangi shop o'sha nom/manzil bilan ochiladi.
        $res = $this->postJson("/api/mobile/dealers/{$target->id}/join", [
            'from_shop_id' => $joyShop->id,
        ])->assertCreated();

        $newShopId = $res->json('shop_id');
        $this->assertDatabaseHas('shops', [
            'id' => $newShopId,
            'dealer_id' => $target->id,
            'name' => 'Anhor do\'kon',
            'address' => 'Toshkent sh., Yunusobod',
        ]);

        // Shu joyga qayta ulanish — dublikat yaratmaydi, o'sha shopni qaytaradi.
        $this->postJson("/api/mobile/dealers/{$target->id}/join", [
            'from_shop_id' => $joyShop->id,
        ])->assertOk()->assertJsonPath('shop_id', $newShopId);

        $this->assertSame(
            1,
            Shop::query()->where('dealer_id', $target->id)->count(),
        );
    }
}
