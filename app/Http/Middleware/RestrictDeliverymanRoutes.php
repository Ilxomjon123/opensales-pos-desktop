<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthorized;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yetkazib beruvchi faqat o'z menyusidagi sahifalarni ochishi mumkin.
 * Ruxsat berilmagan diller route'lariga kirsa default sahifaga redirect bo'ladi.
 */
final class RestrictDeliverymanRoutes
{
    use RedirectsUnauthorized;

    private const ALLOWED_PREFIXES = [
        'dealer.routes.',
        'dealer.orders.',
        'dealer.shops.',
        'dealer.carry.',
        'dealer.courier-cash.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isDeliveryman()) {
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
