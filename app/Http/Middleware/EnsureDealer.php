<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthorized;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDealer
{
    use RedirectsUnauthorized;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if (! $user->canManageDealer() || $user->dealer_id === null) {
            return $this->redirectUnauthorized($user);
        }

        return $next($request);
    }
}
