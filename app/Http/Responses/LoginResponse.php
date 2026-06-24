<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();

        // DELIVERYMAN — bir vaqtda faqat 1 ta aktiv sessiya bo'ladi.
        // Yangi login eski session/tokenlarni o'chiradi (bitta akkountni ko'p
        // dostavchik orasida ulashish imkonini cheklaydi).
        if ($user?->role === UserRole::DELIVERYMAN) {
            $currentSessionId = $request->session()->getId();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            $user->tokens()->delete();
        }

        $home = match ($user?->role) {
            UserRole::SUPER_ADMIN => '/admin/dealers',
            UserRole::DEALER => '/dealer/stats',
            UserRole::WAREHOUSE => '/dealer/orders',
            UserRole::DELIVERYMAN => '/dealer/routes/today',
            default => '/dashboard',
        };

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        // Yetkazib beruvchini doim o'z bosh sahifasiga yo'naltiramiz —
        // intended URL (masalan, /dealer/products) tasodifan ochilib qolmasin.
        if ($user?->role === UserRole::DELIVERYMAN) {
            return redirect($home);
        }

        return redirect()->intended($home);
    }
}
