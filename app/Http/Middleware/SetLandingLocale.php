<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landing sahifalari uchun tilni URL prefiksidan oladi: `/`, `/ru`, `/en`,
 * `/uz-Cyrl`. Default (uz) prefiksiz. Tanlangan til `route()` havolalarida
 * saqlanadi (URL::defaults) va cookie'ga yoziladi — SPA boot va keyingi
 * so'rovlar bir xil tilni ko'rsatishi uchun.
 */
final class SetLandingLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('locales.supported', []));
        $default = (string) config('locales.default', 'uz');
        $locale = $request->route('locale');

        if (is_string($locale) && in_array($locale, $supported, true) && $locale !== $default) {
            // Lokalizatsiya prefiksi landing route() havolalarida saqlanadi.
            URL::defaults(['locale' => $locale]);
        } else {
            $locale = $default;
        }

        // `locale` route parametri controller'ga uzatilmasin — aks holda
        // pozitsion dispatch model-binding argumentini siljitadi.
        $request->route()?->forgetParameter('locale');

        App::setLocale($locale);

        $response = $next($request);

        $response->headers->setCookie(Cookie::create(
            name: 'locale',
            value: $locale,
            expire: now()->addYear()->getTimestamp(),
            path: '/',
            secure: $request->isSecure(),
            httpOnly: false,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
