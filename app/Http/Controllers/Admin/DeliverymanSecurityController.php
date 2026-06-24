<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeliverymanSecurityService;
use Illuminate\Http\RedirectResponse;

final class DeliverymanSecurityController extends Controller
{
    public function __construct(private readonly DeliverymanSecurityService $security) {}

    /**
     * Faqat super admin DELIVERYMAN qulfini ocha oladi.
     * Diller fraudster bo'lishi mumkinligi sababli, qulfni o'zi ocha olmaydi
     * (FIXED_PER_DELIVERYMAN komissiyani aylanib o'tish urinishlarini cheklash).
     */
    public function unlock(User $deliveryman): RedirectResponse
    {
        abort_unless($deliveryman->role === UserRole::DELIVERYMAN, 404);

        $this->security->unlock($deliveryman);

        return back()->with('status', 'Yetkazib beruvchi akkounti qulfdan ochildi');
    }
}
