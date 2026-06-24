<?php

declare(strict_types=1);

/**
 * Nutgram bot handler routing.
 *
 * Bot faqat mini app'ga yo'naltirish va registratsiya uchun ishlaydi.
 * Barcha buyurtma/katalog/hisob amallari mini app orqali bajariladi.
 *
 * @var Nutgram $bot
 */

use App\Telegram\Handlers\LanguageHandler;
use App\Telegram\Handlers\LinkAppHandler;
use App\Telegram\Handlers\StartHandler;
use SergiX44\Nutgram\Nutgram;

// Deep-link: /start TOKEN — token bilan biriktirish
$bot->onCommand('start {token}', [StartHandler::class, 'handle']);

// Oddiy /start — param siz
$bot->onCommand('start', [StartHandler::class, 'handle'])
    ->description('Botni ishga tushirish');

// Til tanlash — /language buyrug'i, "🌐 Til" tugmasi va til tanlovi callback'i
$bot->onCommand('language', [LanguageHandler::class, 'choose'])
    ->description('Til / Language');

// Mobil ilovani ulash — bir martalik kod
$bot->onCommand('link', [LinkAppHandler::class, 'handle'])
    ->description('Mobil ilovani ulash');
$bot->onCallbackQueryData('lang', [LanguageHandler::class, 'choose']);
$bot->onCallbackQueryData('setlang:{code}', [LanguageHandler::class, 'set']);

// Har qanday boshqa xabar yoki callback — do'konga yo'naltirish
$bot->fallback([StartHandler::class, 'fallback']);
