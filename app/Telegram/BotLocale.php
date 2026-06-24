<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Models\Dealer;
use App\Models\ShopMember;
use Illuminate\Support\Facades\App;
use SergiX44\Nutgram\Nutgram;

/**
 * Bot tilini aniqlaydi va o'rnatadi. Ustuvorlik:
 * 1. ShopMember.locale (ro'yxatdan o'tgan mijoz tanlovi)
 * 2. Bot user-data ga saqlangan tanlov (ro'yxatdan oldin)
 * 3. Telegram mijoz tilining kodi (language_code)
 * 4. config('locales.default')
 */
final class BotLocale
{
    public const USER_DATA_KEY = 'locale';

    /**
     * Aniqlangan tilni App::setLocale orqali o'rnatadi va qaytaradi.
     */
    public static function apply(Nutgram $bot, ?Dealer $dealer = null): string
    {
        $locale = self::resolve($bot, $dealer);
        App::setLocale($locale);

        return $locale;
    }

    /**
     * Mijoz a'zosi (yoki har qanday) saqlangan til kodidan qo'llab-quvvatlanadigan
     * tilni qaytaradi — bildirishnomalarni qabul qiluvchi tilida yuborish uchun.
     */
    public static function fromStored(?string $locale): string
    {
        $supported = self::supported();

        return ($locale !== null && in_array($locale, $supported, true))
            ? $locale
            : (string) config('locales.default', 'uz');
    }

    /**
     * Saqlangan til kodini App locale sifatida o'rnatadi va qaytaradi.
     */
    public static function applyStored(?string $locale): string
    {
        $resolved = self::fromStored($locale);
        App::setLocale($resolved);

        return $resolved;
    }

    public static function resolve(Nutgram $bot, ?Dealer $dealer = null): string
    {
        $supported = self::supported();
        $default = (string) config('locales.default', 'uz');

        $member = self::member($bot, $dealer);

        if ($member?->locale !== null && in_array($member->locale, $supported, true)) {
            return $member->locale;
        }

        $stored = $bot->getUserData(self::USER_DATA_KEY);

        if (is_string($stored) && in_array($stored, $supported, true)) {
            return $stored;
        }

        $tgLang = $bot->user()?->language_code;

        if (is_string($tgLang) && in_array($tgLang, $supported, true)) {
            return $tgLang;
        }

        return $default;
    }

    /**
     * Tanlangan tilni saqlaydi: mijoz mavjud bo'lsa DB ga, aks holda user-data ga.
     */
    public static function persist(Nutgram $bot, string $locale, ?Dealer $dealer = null): void
    {
        if (! in_array($locale, self::supported(), true)) {
            return;
        }

        $member = self::member($bot, $dealer);

        if ($member !== null) {
            $member->forceFill(['locale' => $locale])->save();
        }

        // user-data ham yangilaymiz — keyingi so'rovlarda DB query siz ishlaydi
        $bot->setUserData(self::USER_DATA_KEY, $locale);
        App::setLocale($locale);
    }

    private static function member(Nutgram $bot, ?Dealer $dealer): ?ShopMember
    {
        $telegramId = $bot->userId();
        $dealer ??= app()->bound(Dealer::class) ? app(Dealer::class) : null;

        if ($telegramId === null || $dealer === null) {
            return null;
        }

        return ShopMember::query()
            ->forTelegram((int) $telegramId)
            ->whereHas('shop', fn ($q) => $q->forDealer($dealer->id))
            ->first();
    }

    /**
     * @return list<string>
     */
    private static function supported(): array
    {
        return array_keys((array) config('locales.supported', []));
    }
}
