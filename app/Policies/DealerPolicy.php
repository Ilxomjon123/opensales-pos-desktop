<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dealer;
use App\Models\User;

final class DealerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Dealer $dealer): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Dealer $dealer): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Dealer $dealer): bool
    {
        return $user->isSuperAdmin();
    }
}
