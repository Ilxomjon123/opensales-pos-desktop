<?php

declare(strict_types=1);

use App\Http\Controllers\MiniApp\CartController;
use App\Http\Controllers\MiniApp\CatalogController;
use App\Http\Controllers\MiniApp\FavoriteController;
use App\Http\Controllers\MiniApp\OrderController;
use App\Http\Controllers\MiniApp\ProfileController;
use App\Http\Middleware\ValidateTelegramWebApp;
use App\Models\Dealer;
use Illuminate\Support\Facades\Route;

// Mini App SPA sahifasi (Blade)
Route::get('/miniapp/{dealer}', function (Dealer $dealer) {
    abort_if(! $dealer->is_active, 404);

    return view('miniapp', ['dealer' => $dealer]);
})->name('miniapp');

// Mini App JSON API
Route::prefix('api/miniapp/{dealer}')
    ->middleware(ValidateTelegramWebApp::class)
    ->group(function (): void {
        Route::get('me', [ProfileController::class, 'me']);
        Route::get('info', [ProfileController::class, 'info']);
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
