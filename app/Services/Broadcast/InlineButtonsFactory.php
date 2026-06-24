<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

/**
 * Saqlangan buttons konfiguratsiyasidan (qatorlar × tugmalar) Telegram inline
 * klaviaturasini quradi. Bo'sh matn yoki URL'li tugmalar tashlab yuboriladi.
 * Hech bir to'g'ri tugma bo'lmasa null qaytadi.
 *
 * O'z mini app'imizga ishora qiluvchi URL (masalan https://opensales.uz/miniapp/3)
 * brauzerda emas, Telegram mini app sifatida ochilishi uchun web_app tugma bo'ladi.
 */
final class InlineButtonsFactory
{
    /**
     * @param  array<int,mixed>|null  $rows
     */
    public function fromRows(?array $rows): ?InlineKeyboardMarkup
    {
        if ($rows === null || $rows === []) {
            return null;
        }

        $markup = InlineKeyboardMarkup::make();
        $added = false;

        foreach ($rows as $row) {
            $rowButtons = [];

            foreach ((array) $row as $btn) {
                $text = (string) ($btn['text'] ?? '');
                $url = (string) ($btn['url'] ?? '');

                if ($text === '' || $url === '') {
                    continue;
                }

                $rowButtons[] = $this->isMiniAppUrl($url)
                    ? new InlineKeyboardButton(text: $text, web_app: new WebAppInfo(url: $url))
                    : new InlineKeyboardButton(text: $text, url: $url);
            }

            if ($rowButtons !== []) {
                $markup->addRow(...$rowButtons);
                $added = true;
            }
        }

        return $added ? $markup : null;
    }

    /**
     * URL mini app sahifasiga ishora qiladimi (https + /miniapp/...).
     * Shu holatda web_app tugma quriladi va Telegram ichida ochiladi.
     * Domen botga BotFather orqali bog'langan bo'lishi kerak (opensales.uz).
     */
    private function isMiniAppUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['path'])) {
            return false;
        }

        return strcasecmp($parts['scheme'], 'https') === 0
            && str_starts_with($parts['path'], '/miniapp/');
    }
}
