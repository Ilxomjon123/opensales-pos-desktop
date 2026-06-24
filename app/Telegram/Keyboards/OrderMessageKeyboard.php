<?php

declare(strict_types=1);

namespace App\Telegram\Keyboards;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

/**
 * "Buyurtmani ochish" — mini app ichida aniq buyurtmani ochuvchi web_app tugma.
 */
final class OrderMessageKeyboard
{
    public static function make(int $dealerId, int $telegramId, int $orderId, ?int $shopId = null, ?int $messageId = null): InlineKeyboardMarkup
    {
        $url = rtrim((string) config('app.url'), '/')
            ."/miniapp/{$dealerId}?tg_id={$telegramId}&order={$orderId}";

        if ($shopId !== null && $shopId > 0) {
            $url .= "&shop_id={$shopId}";
        }

        if ($messageId !== null && $messageId > 0) {
            $url .= "&msg={$messageId}";
        }

        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(
                text: __('bot.button.open_order'),
                web_app: new WebAppInfo(url: $url),
            ),
        );
    }
}
