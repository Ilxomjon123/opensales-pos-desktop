<?php

declare(strict_types=1);

namespace App\Telegram\Keyboards;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Til tanlash inline klaviaturasi — config('locales.supported') dan quriladi.
 * Har bir tugma callback_data sifatida "setlang:<code>" yuboradi.
 */
final class LanguageKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $markup = InlineKeyboardMarkup::make();

        /** @var array<string, array{code: string, native: string, flag: string}> $locales */
        $locales = (array) config('locales.supported', []);

        foreach ($locales as $locale) {
            $markup->addRow(
                InlineKeyboardButton::make(
                    text: trim(($locale['flag'] ?? '').' '.($locale['native'] ?? $locale['code'])),
                    callback_data: 'setlang:'.$locale['code'],
                ),
            );
        }

        return $markup;
    }
}
