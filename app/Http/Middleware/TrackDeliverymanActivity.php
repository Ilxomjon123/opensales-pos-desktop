<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\DeliverymanSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class TrackDeliverymanActivity
{
    public function __construct(private readonly DeliverymanSecurityService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::DELIVERYMAN) {
            return $next($request);
        }

        if ($user->security_locked_until !== null && $user->security_locked_until->isFuture()) {
            return $this->logoutAndRedirect($request, $user->security_locked_until->diffForHumans());
        }

        $allowed = $this->security->track($user, (string) $request->ip());

        if (! $allowed) {
            $until = $user->fresh()?->security_locked_until;
            $msg = $until !== null && $until->isFuture()
                ? 'Akkount xavfsizlik buzilishi sababli muzlatildi: '.$until->diffForHumans()
                : 'Bir vaqtda boshqa qurilmadan kirilgan. Qayta login qiling.';

            return $this->logoutAndRedirect($request, $msg);
        }

        return $next($request);
    }

    private function logoutAndRedirect(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            abort(401, $message);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
