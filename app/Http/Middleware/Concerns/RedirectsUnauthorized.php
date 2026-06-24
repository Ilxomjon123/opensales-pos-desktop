<?php

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

trait RedirectsUnauthorized
{
    /**
     * Foydalanuvchini default sahifasiga yo'naltirish va ruxsat yo'qligi haqida
     * toast ko'rsatish. 403 chiqarmaydi.
     */
    private function redirectUnauthorized(User $user): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => "Bu sahifaga ruxsatingiz yo'q",
        ]);

        return redirect()->route($user->defaultRouteName());
    }
}
