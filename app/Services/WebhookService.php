<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WebhookServiceInterface;
use App\Http\Middleware\VerifyTelegramWebhook;
use App\Models\Dealer;
use App\Telegram\BotFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonDefault;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;
use Throwable;

final class WebhookService implements WebhookServiceInterface
{
    public function __construct(
        private readonly BotFactory $botFactory,
    ) {}

    public function register(Dealer $dealer): bool
    {
        if ($dealer->bot_token === null) {
            return false;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        // Bot username'ni avval sync qilamiz — webhook (HTTPS talab qiladi, local'da
        // o'rnatilmasligi mumkin) muvaffaqiyatidan qat'i nazar username saqlanadi.
        $this->syncBotUsername($bot, $dealer);

        try {
            $bot->setWebhook(
                url: $this->url($dealer),
                drop_pending_updates: true,
                allowed_updates: ['message', 'callback_query'],
                secret_token: VerifyTelegramWebhook::secretToken($dealer),
            );

            $dealer->forceFill(['webhook_set_at' => now()])->save();

            $this->applyMenuButton($bot, $dealer);
            $this->applyBotProfile($bot, $dealer);

            Log::info("Webhook registered for dealer #{$dealer->id}", [
                'url' => $this->url($dealer),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Webhook registration failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function remove(Dealer $dealer): bool
    {
        if ($dealer->bot_token === null) {
            return false;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        try {
            $bot->deleteWebhook(drop_pending_updates: true);
            $this->applyDefaultMenuButton($bot, $dealer);

            $dealer->forceFill(['webhook_set_at' => null])->save();

            Log::info("Webhook removed for dealer #{$dealer->id}");

            return true;
        } catch (Throwable $e) {
            Log::error("Webhook removal failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Webhook holatini tekshirish.
     *
     * @return array{url: string, pending_update_count: int, last_error_message: string|null, last_error_date: int|null}|null
     */
    public function getInfo(Dealer $dealer): ?array
    {
        // Token biriktirilmagan diller (self-registratsiya) — webhook holati yo'q.
        if (($dealer->bot_token ?? '') === '') {
            return null;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        try {
            $info = $bot->getWebhookInfo();

            if ($info === null) {
                return null;
            }

            return [
                'url' => $info->url,
                'pending_update_count' => $info->pending_update_count,
                'last_error_message' => $info->last_error_message,
                'last_error_date' => $info->last_error_date,
            ];
        } catch (Throwable $e) {
            Log::warning("Webhook info fetch failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Bot tokenni tekshirish — username qaytaradi yoki null.
     *
     * Natija 60 sekund cache da saqlanadi: foydalanuvchi tezda formani saqlaganda
     * Telegram API ga qayta so'rov ketmaydi. Xato natija (null) faqat 10 sekund
     * cache da turadi — token tuzatilsa tezda qayta tekshirsin.
     */
    public function verifyToken(string $token): ?string
    {
        $cacheKey = 'tg:verify:'.hash('sha256', $token);

        $cached = Cache::get($cacheKey, false);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $bot = $this->botFactory->make($token);
            $username = $bot->getMe()?->username;

            Cache::put($cacheKey, $username, $username !== null ? 60 : 10);

            return $username;
        } catch (Throwable $e) {
            Log::warning('Bot token verification failed', [
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, null, 10);

            return null;
        }
    }

    public function url(Dealer $dealer): string
    {
        $base = trim((string) config('nutgram.webhook_base_url', ''));

        if ($base === '') {
            return route('telegram.webhook', ['dealer' => $dealer->id]);
        }

        return rtrim($base, '/').'/webhook/'.$dealer->id;
    }

    public function setMenuButton(Dealer $dealer): bool
    {
        if ($dealer->bot_token === null) {
            return false;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        return $this->applyMenuButton($bot, $dealer);
    }

    public function resetMenuButton(Dealer $dealer): bool
    {
        if ($dealer->bot_token === null) {
            return false;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        return $this->applyDefaultMenuButton($bot, $dealer);
    }

    public function applyProfile(Dealer $dealer): bool
    {
        if ($dealer->bot_token === null) {
            return false;
        }

        $bot = $this->botFactory->make($dealer->bot_token);

        return $this->applyBotProfile($bot, $dealer);
    }

    /**
     * Bot profilini (nom, qisqa va to'liq tavsif) Telegram'ga yuborish.
     * Default qiymatlar Dealer effective* metodlaridan olinadi.
     */
    private function applyBotProfile(Nutgram $bot, Dealer $dealer): bool
    {
        $name = $dealer->effectiveBotDisplayName();
        $shortDescription = $dealer->effectiveBotShortDescription();
        $description = $dealer->effectiveBotDescription();

        $ok = true;

        try {
            $bot->setMyName(name: $name);
        } catch (Throwable $e) {
            $ok = false;
            Log::warning("Bot name set failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $bot->setMyShortDescription(short_description: $shortDescription);
        } catch (Throwable $e) {
            $ok = false;
            Log::warning("Bot short description set failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $bot->setMyDescription(description: $description);
        } catch (Throwable $e) {
            $ok = false;
            Log::warning("Bot description set failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        return $ok;
    }

    private function applyMenuButton(Nutgram $bot, Dealer $dealer): bool
    {
        try {
            $bot->setChatMenuButton(
                menu_button: new MenuButtonWebApp(
                    text: 'Buyurtma berish',
                    web_app: new WebAppInfo(url: $this->miniAppUrl($dealer)),
                ),
            );

            return true;
        } catch (Throwable $e) {
            Log::warning("Menu button set failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function applyDefaultMenuButton(Nutgram $bot, Dealer $dealer): bool
    {
        try {
            $bot->setChatMenuButton(menu_button: new MenuButtonDefault);

            return true;
        } catch (Throwable $e) {
            Log::debug("Menu button reset failed for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function miniAppUrl(Dealer $dealer): string
    {
        return route('miniapp', ['dealer' => $dealer->id]);
    }

    private function syncBotUsername(Nutgram $bot, Dealer $dealer): void
    {
        try {
            $me = $bot->getMe();

            if ($me?->username !== null && $me->username !== $dealer->bot_username) {
                $dealer->forceFill(['bot_username' => $me->username])->save();
            }
        } catch (Throwable $e) {
            Log::debug("Bot username sync skipped for dealer #{$dealer->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
