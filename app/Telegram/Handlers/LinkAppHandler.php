<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Models\Dealer;
use App\Services\MobileAppLinkService;
use App\Telegram\BotLocale;
use App\Telegram\ShopResolver;
use SergiX44\Nutgram\Nutgram;

/**
 * /link — mobil ilovani bot akkauntiga ulash uchun bir martalik kod beradi.
 * Foydalanuvchi kodni mobil ilovada (telefon bilan kirgach) kiritadi —
 * bot vakillari shu mijoz akkauntiga birlashtiriladi.
 */
final class LinkAppHandler
{
    public function __construct(
        private readonly ShopResolver $resolver,
        private readonly MobileAppLinkService $linkService,
        private readonly Dealer $dealer,
    ) {}

    public function handle(Nutgram $bot): void
    {
        BotLocale::apply($bot, $this->dealer);

        $member = $this->resolver->resolveMember($bot);

        if ($member === null) {
            $bot->sendMessage('Avval do\'kon sifatida ro\'yxatdan o\'ting.');

            return;
        }

        $code = $this->linkService->issueCode((int) $bot->userId());

        $bot->sendMessage(
            "📱 Mobil ilova uchun ulash kodi:\n\n<b>{$code}</b>\n\n".
            'Kod 10 daqiqa amal qiladi. Ilovada telefon bilan kirgach, shu kodni kiriting.',
            parse_mode: 'HTML',
        );
    }
}
