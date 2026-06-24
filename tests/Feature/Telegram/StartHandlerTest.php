<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Exceptions\Domain\InvalidInviteException;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopInvite;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\PublicShopRegistrationService;
use App\Services\ShopInviteService;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\ShopResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

final class StartHandlerTest extends TestCase
{
    use RefreshDatabase;

    private const TELEGRAM_ID = 12345;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dealer = Dealer::factory()->create();
        $this->app->instance(Dealer::class, $this->dealer);
        $this->app->bind(ShopResolver::class, fn () => new ShopResolver($this->dealer));
    }

    private function fakeBot(): Nutgram
    {
        return Nutgram::fake(config: new Configuration(container: $this->app));
    }

    public function test_bound_member_sees_main_menu(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['name' => 'TestShop', 'balance' => 50_000]);
        ShopMember::factory()->for($shop)->create(['telegram_id' => self::TELEGRAM_ID]);

        $bot = $this->fakeBot();
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage(['text' => '/start', 'from' => ['id' => self::TELEGRAM_ID]])
            ->reply()
            ->assertReply('sendMessage');
    }

    public function test_interaction_clears_blocked_flag(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['name' => 'TestShop', 'balance' => 0]);
        $member = ShopMember::factory()->for($shop)->create([
            'telegram_id' => self::TELEGRAM_ID,
            'blocked_at' => now()->subDay(),
        ]);

        $bot = $this->fakeBot();
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage(['text' => '/start', 'from' => ['id' => self::TELEGRAM_ID]])->reply();

        $this->assertNull($member->fresh()->blocked_at);
    }

    public function test_unbound_user_is_prompted_to_ask_for_invite(): void
    {
        $bot = $this->fakeBot();
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage(['text' => '/start', 'from' => ['id' => 99999]])
            ->reply()
            ->assertReply('sendMessage');
    }

    public function test_shop_invite_service_binds_user_to_shop(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $creator = User::factory()->deliveryman($this->dealer->id)->create();
        $invite = app(ShopInviteService::class)->createForShop($shop, $creator);

        $member = app(ShopInviteService::class)->redeem(
            token: $invite->token,
            telegramId: 5555,
            name: 'Ali',
            username: 'alibaba',
        );

        $this->assertSame($shop->id, $member->shop_id);
        $this->assertSame(5555, $member->telegram_id);
        $this->assertNotNull($invite->fresh()->used_at);
    }

    public function test_owner_link_sets_dealer_notification_chat(): void
    {
        $this->dealer->forceFill(['owner_link_token' => 'own_secret123', 'telegram_chat_id' => null])->save();

        $bot = $this->fakeBot();
        $bot->onCommand('start {token}', [StartHandler::class, 'handle']);
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage([
            'text' => '/start own_secret123',
            'from' => ['id' => 4242],
            'chat' => ['id' => 4242, 'type' => 'private'],
        ])->reply();

        $fresh = $this->dealer->fresh();
        $this->assertSame(4242, $fresh->telegram_chat_id);
        $this->assertNull($fresh->owner_link_token);
    }

    public function test_owner_link_rejects_wrong_token(): void
    {
        $this->dealer->forceFill(['owner_link_token' => 'own_secret123', 'telegram_chat_id' => null])->save();

        $bot = $this->fakeBot();
        $bot->onCommand('start {token}', [StartHandler::class, 'handle']);
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage([
            'text' => '/start own_wrong',
            'from' => ['id' => 4242],
            'chat' => ['id' => 4242, 'type' => 'private'],
        ])->reply();

        $this->assertNull($this->dealer->fresh()->telegram_chat_id);
    }

    public function test_redeem_rejects_invalid_token(): void
    {
        $this->expectException(InvalidInviteException::class);

        app(ShopInviteService::class)->redeem(
            token: 'inv_notreal',
            telegramId: 7777,
        );
    }

    public function test_redeem_rejects_expired_invite(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $creator = User::factory()->deliveryman($this->dealer->id)->create();
        $invite = app(ShopInviteService::class)->createForShop($shop, $creator);
        $invite->update(['expires_at' => now()->subMinute()]);

        $this->expectException(InvalidInviteException::class);
        app(ShopInviteService::class)->redeem($invite->token, 7777);
    }

    public function test_deep_link_via_bot_handler_binds_user(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $creator = User::factory()->deliveryman($this->dealer->id)->create();
        $invite = app(ShopInviteService::class)->createForShop($shop, $creator);

        $bot = $this->fakeBot();
        $bot->onCommand('start {token}', [StartHandler::class, 'handle']);
        $bot->onCommand('start', [StartHandler::class, 'handle']);

        $bot->hearMessage([
            'text' => "/start {$invite->token}",
            'from' => ['id' => 5555, 'first_name' => 'Ali', 'username' => 'ali'],
        ])->reply();

        $this->assertDatabaseHas('shop_members', [
            'shop_id' => $shop->id,
            'telegram_id' => 5555,
        ]);

        $this->assertNotNull($invite->fresh()->used_at);
    }

    public function test_redeem_rejects_used_invite(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $creator = User::factory()->deliveryman($this->dealer->id)->create();
        $invite = app(ShopInviteService::class)->createForShop($shop, $creator);

        app(ShopInviteService::class)->redeem($invite->token, 111);

        $this->expectException(InvalidInviteException::class);
        app(ShopInviteService::class)->redeem($invite->token, 222);
    }

    public function test_redeem_auto_rotates_invite_for_shop(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create();
        $creator = User::factory()->deliveryman($this->dealer->id)->create();
        $invite = app(ShopInviteService::class)->createForShop($shop, $creator);

        app(ShopInviteService::class)->redeem($invite->token, 333);

        $fresh = ShopInvite::query()
            ->where('shop_id', $shop->id)
            ->valid()
            ->latest('id')
            ->first();

        $this->assertNotNull($fresh, 'Ishlatilgan invitedan keyin yangi valid invite yaratilishi kerak');
        $this->assertNotSame($invite->token, $fresh->token);
        $this->assertSame($creator->id, $fresh->created_by);
    }

    public function test_public_registration_service_creates_shop_and_member(): void
    {
        $dealer = Dealer::factory()->public()->create();

        $member = app(PublicShopRegistrationService::class)->register(
            dealer: $dealer,
            telegramId: 777_888,
            shopName: 'Ali Valiyev',
            address: 'Toshkent, Yunusobod, A.Temur 12',
            latitude: 41.31,
            longitude: 69.28,
            phone: '+998901234567',
            username: 'aliusername',
        );

        $this->assertSame('Ali Valiyev', $member->name);
        $this->assertSame(777_888, $member->telegram_id);
        $this->assertTrue($member->is_active);

        $shop = $member->shop;
        $this->assertSame($dealer->id, $shop->dealer_id);
        $this->assertSame('Ali Valiyev', $shop->name);
        $this->assertSame('Toshkent, Yunusobod, A.Temur 12', $shop->address);
        $this->assertSame('+998901234567', $shop->phone);
        $this->assertEqualsWithDelta(41.31, (float) $shop->latitude, 0.001);
        $this->assertSame(0, (int) $shop->balance);
        $this->assertTrue($shop->is_active);
    }

    public function test_public_registration_creates_separate_shop_per_address(): void
    {
        $dealer = Dealer::factory()->public()->create();
        $service = app(PublicShopRegistrationService::class);

        $first = $service->register(
            dealer: $dealer,
            telegramId: 555,
            shopName: 'Bobur',
            address: 'Uy: Chilonzor 5',
        );

        $second = $service->register(
            dealer: $dealer,
            telegramId: 555,
            shopName: 'Bobur',
            address: 'Ish: Mustaqillik 1',
        );

        $this->assertNotSame($first->shop_id, $second->shop_id);
        $this->assertSame(2, ShopMember::query()->where('telegram_id', 555)->count());
    }

    public function test_dealer_is_public_helper_reflects_visibility(): void
    {
        $public = Dealer::factory()->public()->create();
        $private = Dealer::factory()->create();

        $this->assertTrue($public->isPublic());
        $this->assertFalse($private->isPublic());
    }
}
