<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class LocaleController extends Controller
{
    public function switch(Request $request, string $code): RedirectResponse
    {
        $supported = array_keys(config('locales.supported'));

        abort_unless(in_array($code, $supported, true), 422);

        $cookie = Cookie::create(
            name: 'locale',
            value: $code,
            expire: now()->addYear()->getTimestamp(),
            path: '/',
            secure: $request->isSecure(),
            httpOnly: false,
            sameSite: Cookie::SAMESITE_LAX,
        );

        return back()->withCookie($cookie);
    }
}
