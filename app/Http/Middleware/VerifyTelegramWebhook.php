<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Dealer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram webhook so'rovini tekshirish.
 *
 * setWebhook da secret_token sifatida md5(APP_KEY . dealer.id) yuboriladi.
 * Telegram har so'rovda buni X-Telegram-Bot-Api-Secret-Token header da qaytaradi.
 *
 * Production da majburiy, local da o'tkazib yuboriladi.
 */
final class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }

        /** @var Dealer|null $dealer */
        $dealer = $request->route('dealer');

        if ($dealer === null) {
            abort(404);
        }

        $expected = self::secretToken($dealer);
        $actual = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($actual === null || ! hash_equals($expected, $actual)) {
            abort(403, 'Invalid webhook secret token');
        }

        return $next($request);
    }

    public static function secretToken(Dealer $dealer): string
    {
        return md5(config('app.key').$dealer->id);
    }
}
