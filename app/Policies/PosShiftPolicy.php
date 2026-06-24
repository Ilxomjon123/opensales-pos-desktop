<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PosShift;
use App\Models\User;

final class PosShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManagePos() || $user->isCashier();
    }

    public function view(User $user, PosShift $shift): bool
    {
        if ($user->dealer_id !== $shift->dealer_id) {
            return false;
        }
        if ($user->canManagePos()) {
            return true;
        }

        return $user->isCashier() && $user->id === $shift->cashier_user_id;
    }

    public function open(User $user): bool
    {
        return $user->canRunPos() && $user->dealer_id !== null;
    }

    public function close(User $user, PosShift $shift): bool
    {
        if ($user->dealer_id !== $shift->dealer_id) {
            return false;
        }
        if ($user->canManagePos()) {
            return true;
        }

        return $user->isCashier() && $user->id === $shift->cashier_user_id;
    }
}
