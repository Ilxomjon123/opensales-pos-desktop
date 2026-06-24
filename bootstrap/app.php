<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnsureDealer;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictCashierRoutes;
use App\Http\Middleware\RestrictDeliverymanRoutes;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackDeliverymanActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->group(base_path('routes/webhook.php'));

            Route::middleware('api')
                ->group(base_path('routes/mobile.php'));

            Route::middleware('web')
                ->group(base_path('routes/miniapp.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);

        $middleware->web(prepend: [
            SetLocale::class,
        ], append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EncryptHistory::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'dealer' => EnsureDealer::class,
            'super_admin' => EnsureSuperAdmin::class,
            'track.deliveryman' => TrackDeliverymanActivity::class,
            'restrict.deliveryman' => RestrictDeliverymanRoutes::class,
            'restrict.cashier' => RestrictCashierRoutes::class,
        ]);

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'api/miniapp/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->renderable(function (DomainException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*', 'webhook/*')) {
                return response()->json(array_filter([
                    'message' => $e->getMessage(),
                    'code' => $e->errorCode(),
                ], static fn ($value): bool => $value !== null), 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        });
    })->create();
