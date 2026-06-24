<?php

declare(strict_types=1);

namespace App\Telegram\Keyboards;

use App\Models\Dealer;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

final class MainMenuKeyboard
{
    public static function make(Dealer $dealer, int $telegramId): InlineKeyboardMarkup
    {
        $url = rtrim(config('app.url'), '/')."/miniapp/{$dealer->id}?tg_id={$telegramId}";

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: __('bot.button.order'),
                    web_app: new WebAppInfo(url: $url),
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: __('bot.language.button'),
                    callback_data: 'lang',
                ),
            );
    }
}
