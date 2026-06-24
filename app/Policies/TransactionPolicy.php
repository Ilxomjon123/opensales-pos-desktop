<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

final class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageDealer();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->canManageDealer() && $user->dealer_id === $transaction->dealer_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }
}
