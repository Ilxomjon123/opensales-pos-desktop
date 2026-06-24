<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

final class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->canManageInventory() && $user->dealer_id === $supplier->dealer_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->canManageInventory() && $user->dealer_id === $supplier->dealer_id;
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->update($user, $supplier);
    }

    public function pay(User $user, Supplier $supplier): bool
    {
        return $user->isDealer() && $user->dealer_id === $supplier->dealer_id;
    }
}
