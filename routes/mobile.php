<?php

declare(strict_types=1);

use App\Http\Controllers\MiniApp\CartController;
use App\Http\Controllers\MiniApp\CatalogController;
use App\Http\Controllers\MiniApp\FavoriteController;
use App\Http\Controllers\MiniApp\OrderController;
use App\Http\Controllers\MiniApp\ProfileController;
use App\Http\Controllers\Mobile\MobileAuthController;
use App\Http\Controllers\Mobile\MobileConfigController;
use App\Http\Controllers\Mobile\MobileDeviceTokenController;
use App\Http\Controllers\Mobile\MobileGeoController;
use App\Http\Controllers\Mobile\MobileNotificationController;
use App\Http\Controllers\Mobile\MobileOverviewController;
use App\Http\Controllers\Mobile\MobileTelegramAuthController;
use App\Http\Middleware\ResolveMobileMember;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * Mobil ilova API. Token (Sanctum) auth. Per-dealer endpointlar MiniApp
 * controllerlarini qayta ishlatadi — ResolveMobileMember middleware dealer/
 * shop/member atributlarini token (Customer) dan aniqlaydi.
 */
Route::prefix('api/mobile')->middleware(SetLocale::class)->group(function (): void {
    // Ochiq (auth talab qilinmaydi)
    // Funksiya bayroqlari — joriy davlatga mos (login sahifa shu bo'yicha quriladi).
    Route::get('config', [MobileConfigController::class, 'show']);
    Route::post('auth/request-otp', [MobileAuthController::class, 'requestOtp']);
    Route::post('auth/verify-otp', [MobileAuthController::class, 'verifyOtp']);

    // QR (diller invite) orqali parolsiz kirish — login sahifasidan.
    Route::post('auth/qr-login', [MobileAuthController::class, 'qrLogin']);

    // Telegram orqali kirish (SMS'siz)
    Route::post('auth/telegram/start', [MobileTelegramAuthController::class, 'start']);
    Route::post('auth/telegram/poll', [MobileTelegramAuthController::class, 'poll']);
    Route::post('geo/reverse', [MobileGeoController::class, 'reverse']);
    Route::get('geo/regions', [MobileGeoController::class, 'regions']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/register', [MobileAuthController::class, 'register']);
        Route::post('auth/redeem-invite', [MobileAuthController::class, 'redeemInvite']);
        Route::post('auth/link-telegram', [MobileAuthController::class, 'linkTelegram']);
        // Telegramni ulash (kirgan foydalanuvchi) — bot telefon so'ramaydi.
        Route::post('auth/telegram/link/start', [MobileTelegramAuthController::class, 'linkStart']);
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
        Route::post('auth/locale', [MobileAuthController::class, 'setLocale']);
        Route::get('auth/me', [MobileAuthController::class, 'me']);

        Route::get('dealers', [MobileOverviewController::class, 'dealers']);
        Route::post('discovery/dealers', [MobileOverviewController::class, 'discover']);
        Route::post('dealers/{dealer}/join', [MobileOverviewController::class, 'joinDealer']);

        // Bildirishnomalar (dillerlar bo'ylab umumiy) + FCM qurilma tokenlari.
        Route::get('notifications', [MobileNotificationController::class, 'index']);
        Route::get('notifications/unread', [MobileNotificationController::class, 'unread']);
        Route::post('notifications/read-all', [MobileNotificationController::class, 'readAll']);
        Route::post('notifications/read-context', [MobileNotificationController::class, 'readByContext']);
        Route::post('notifications/{notification}/read', [MobileNotificationController::class, 'read']);

        Route::post('device-tokens', [MobileDeviceTokenController::class, 'store']);
        Route::delete('device-tokens', [MobileDeviceTokenController::class, 'destroy']);

        // Diller ichidagi endpointlar — MiniApp controllerlari qayta ishlatiladi.
        Route::prefix('dealers/{dealer}')
            ->middleware(ResolveMobileMember::class)
            ->group(function (): void {
                Route::get('info', [ProfileController::class, 'info']);
                Route::get('me', [ProfileController::class, 'me']);
                Route::get('shops', [ProfileController::class, 'shops']);

                Route::get('orders', [ProfileController::class, 'orders']);
                Route::get('orders/{order}', [OrderController::class, 'show']);
                Route::post('orders/{order}/reorder', [OrderController::class, 'reorder']);
                Route::post('orders/{order}/receive', [OrderController::class, 'receive']);

                Route::get('products', [CatalogController::class, 'products']);
                Route::get('products/{product}', [CatalogController::class, 'product']);
                Route::get('categories', [CatalogController::class, 'categories']);

                Route::get('favorites', [FavoriteController::class, 'index']);
                Route::post('favorites/{product}', [FavoriteController::class, 'toggle']);

                Route::get('cart', [CartController::class, 'show']);
                Route::post('cart/add', [CartController::class, 'add']);
                Route::patch('cart/{productId}', [CartController::class, 'update']);
                Route::delete('cart/{productId}', [CartController::class, 'remove']);
                Route::delete('cart', [CartController::class, 'clear']);
                Route::post('cart/confirm', [CartController::class, 'confirm']);
            });
    });
});
