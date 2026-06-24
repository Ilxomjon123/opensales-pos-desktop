<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Services\TelegramLoginService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

/**
 * OpenSales app-level bot — faqat "Telegram orqali kirish" oqimi uchun.
 * Dillerlar botidan alohida token. /start auth_<token> + contact ulashish.
 */
final class AppBotFactory
{
    public static function make(): ?Nutgram
    {
        $token = config('services.opensales_bot.token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        // BotFactory orqali quramiz — proxy secret, client options, timeoutlar
        // dealer botlari bilan bir xil bo'ladi (Cloudflare worker proxy uchun shart).
        $bot = app(BotFactory::class)->make($token);

        self::register($bot);

        return $bot;
    }

    private static function register(Nutgram $bot): void
    {
        $login = app(TelegramLoginService::class);

        // /start auth_<token> — kirish so'rovi; /start link_<token> — telegramni ulash.
        $bot->onCommand('start {payload}', function (Nutgram $bot, string $payload) use ($login): void {
            $tgId = (int) $bot->userId();

            // Ulash oqimi: allaqachon kirgan foydalanuvchi telegramini bog'laydi.
            // Telefon SO'RALMAYDI — token joriy customer'ga bog'langan.
            if (str_starts_with($payload, 'link_')) {
                $linkToken = substr($payload, 5);
                BotLocale::applyStored($login->tokenLocale($linkToken));

                if (! $login->confirmLink($linkToken, $tgId)) {
                    $bot->sendMessage(__('bot.applogin.invalid'));

                    return;
                }

                $bot->sendMessage(
                    text: __('bot.applogin.success'),
                    parse_mode: ParseMode::HTML,
                    reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
                );
                $bot->sendMessage(
                    text: __('bot.applogin.open_hint'),
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make(
                            text: __('bot.applogin.open_button'),
                            url: route('tg.open', ['token' => $linkToken]),
                        ),
                    ),
                );

                return;
            }

            $token = str_starts_with($payload, 'auth_') ? substr($payload, 5) : null;

            // App tanlagan til — bot xabarlari shu tilda.
            BotLocale::applyStored($token !== null ? $login->tokenLocale($token) : null);

            if ($token === null || ! $login->setPending($tgId, $token)) {
                $bot->sendMessage(__('bot.applogin.invalid'));

                return;
            }

            $bot->sendMessage(
                text: __('bot.applogin.ask_contact'),
                reply_markup: ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)
                    ->addRow(KeyboardButton::make(__('bot.applogin.share_phone'), request_contact: true)),
            );
        });

        // Oddiy /start — yo'riqnoma.
        $bot->onCommand('start', function (Nutgram $bot): void {
            $bot->sendMessage(__('bot.applogin.open_app'));
        });

        // Contact ulashildi — tasdiqlaymiz.
        $bot->onMessage(function (Nutgram $bot) use ($login): void {
            $contact = $bot->message()?->contact;

            if ($contact === null) {
                return;
            }

            $tgId = (int) $bot->userId();
            $pending = $login->pendingToken($tgId);

            BotLocale::applyStored($pending !== null ? $login->tokenLocale($pending) : null);

            if ($pending === null) {
                $bot->sendMessage(__('bot.applogin.open_app'),
                    reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true));

                return;
            }

            $name = trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''));
            $ok = $login->confirm($pending, $tgId, (string) $contact->phone_number, $name === '' ? null : $name);

            // Klaviaturani olib tashlaymiz.
            $bot->sendMessage(
                text: $ok ? __('bot.applogin.success') : __('bot.applogin.invalid'),
                parse_mode: ParseMode::HTML,
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );

            // Muvaffaqiyatda — ilovani ochuvchi inline tugma.
            if ($ok) {
                $bot->sendMessage(
                    text: __('bot.applogin.open_hint'),
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make(
                            text: __('bot.applogin.open_button'),
                            url: route('tg.open', ['token' => $pending]),
                        ),
                    ),
                );
            }
        });
    }
}
