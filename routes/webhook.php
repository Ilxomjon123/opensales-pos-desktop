<?php

declare(strict_types=1);

use App\Http\Controllers\AppBotController;
use App\Http\Controllers\BotController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

// App-level login bot ({dealer} dan oldin — statik yo'l ustun bo'lsin).
Route::post('/webhook/app', [AppBotController::class, 'handle'])->name('telegram.webhook.app');

Route::post('/webhook/{dealer}', [BotController::class, 'handle'])
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');
