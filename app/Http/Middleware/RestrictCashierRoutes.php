<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthorized;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kassir faqat POS modulini ko'radi.
 * Diller dashboard'ining boshqa sahifalariga kirsa default sahifaga (POS Index) redirect bo'ladi.
 */
final class RestrictCashierRoutes
{
    use RedirectsUnauthorized;

    private const ALLOWED_PREFIXES = [
        'dealer.pos.',
        'profile.',
        'password.',
        'two-factor.',
        'security.',
        'verification.',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isCashier()) {
            $name = $request->route()?->getName() ?? '';

            $allowed = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return $this->redirectUnauthorized($user);
            }
        }

        return $next($request);
    }
}
