<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Models\Dealer;
use App\Telegram\BotLocale;
use App\Telegram\Keyboards\LanguageKeyboard;
use SergiX44\Nutgram\Nutgram;

final class LanguageHandler
{
    public function __construct(
        private readonly Dealer $dealer,
        private readonly StartHandler $start,
    ) {}

    /**
     * Til tanlash menyusini ko'rsatadi (/language yoki "🌐 Til" tugmasi).
     */
    public function choose(Nutgram $bot): void
    {
        BotLocale::apply($bot, $this->dealer);

        $bot->sendMessage(
            text: __('bot.language.choose'),
            reply_markup: LanguageKeyboard::make(),
        );
    }

    /**
     * Tanlangan tilni saqlaydi va asosiy menyuni yangi tilda qayta ko'rsatadi.
     */
    public function set(Nutgram $bot, string $code): void
    {
        BotLocale::persist($bot, $code, $this->dealer);

        $bot->answerCallbackQuery(text: __('bot.language.changed'));
        $bot->editMessageText(text: __('bot.language.changed'));

        $this->start->fallback($bot);
    }
}
