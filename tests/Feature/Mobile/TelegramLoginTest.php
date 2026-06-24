<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\TelegramLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TelegramLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_login_creates_customer_and_links_existing_telegram_memberships(): void
    {
        // Botda mavjud telegram foydalanuvchi (mobil'da hali yo'q).
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $member = ShopMember::factory()->create([
            'shop_id' => $shop->id,
            'telegram_id' => 555000111,
            'customer_id' => null,
        ]);

        $svc = app(TelegramLoginService::class);

        $token = $svc->start();
        $this->assertTrue($svc->setPending(555000111, $token));
        $this->assertTrue($svc->confirm($token, 555000111, '998901112233', 'Ali'));

        // Customer yaratildi + telegramdagi a'zolik unga bog'landi.
        $customer = Customer::query()->where('phone', '+998901112233')->first();
        $this->assertNotNull($customer);
        $this->assertSame($customer->id, $member->fresh()->customer_id);

        // Poll — Sanctum token + a'zolik bor.
        $res = $svc->poll($token);
        $this->assertSame('confirmed', $res['status']);
        $this->assertArrayHasKey('token', $res);
        $this->assertTrue($res['has_membership']);

        // Token bir martalik.
        $this->assertSame('expired', $svc->poll($token)['status']);
    }

    public function test_telegram_link_attaches_memberships_to_logged_in_customer_without_phone(): void
    {
        // Allaqachon kirgan mijoz (telefon bilan).
        $customer = Customer::factory()->create(['phone' => '+998901112233']);

        // Botda shu telegram foydalanuvchining do'koni (boshqa/telefonsiz egada).
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $member = ShopMember::factory()->create([
            'shop_id' => $shop->id,
            'telegram_id' => 777000222,
            'customer_id' => null,
        ]);

        $svc = app(TelegramLoginService::class);

        // App (kirgan) ulash tokeni oladi — customer'ga bog'langan.
        $token = $svc->startLink($customer->id);

        // Bot /start link_<token> — telefon SO'RAMAYDI, to'g'ridan-to'g'ri bog'laydi.
        $this->assertTrue($svc->confirmLink($token, 777000222));

        // Telegramdagi a'zolik aynan shu (kirgan) customer'ga bog'landi.
        $this->assertSame($customer->id, $member->fresh()->customer_id);

        // Poll confirmed.
        $this->assertSame('confirmed', $svc->poll($token)['status']);
    }

    public function test_confirm_link_rejects_non_link_token(): void
    {
        $svc = app(TelegramLoginService::class);
        $loginToken = $svc->start(); // oddiy login tokeni, link emas

        $this->assertFalse($svc->confirmLink($loginToken, 123456));
    }

    public function test_link_start_endpoint_requires_auth_and_returns_link_url(): void
    {
        config()->set('services.opensales_bot.username', 'opensales_bot');

        // Auth'siz — 401.
        $this->postJson('/api/mobile/auth/telegram/link/start')->assertUnauthorized();

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/mobile/auth/telegram/link/start')
            ->assertOk()
            ->assertJsonStructure(['token', 'bot_url', 'bot_username'])
            ->assertJsonPath('bot_url', fn ($url) => is_string($url) && str_contains($url, 'start=link_'));
    }

    public function test_poll_pending_before_confirm(): void
    {
        $svc = app(TelegramLoginService::class);
        $token = $svc->start();
        $this->assertSame('pending', $svc->poll($token)['status']);
    }

    public function test_start_endpoint_returns_token(): void
    {
        config()->set('services.opensales_bot.username', 'opensales_bot');

        $this->postJson('/api/mobile/auth/telegram/start')
            ->assertOk()
            ->assertJsonStructure(['token', 'bot_url', 'bot_username']);
    }
}
