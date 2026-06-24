<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

final class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageDealer();
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->canManageDealer() && $user->dealer_id === $category->dealer_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->canManageInventory() && $user->dealer_id === $category->dealer_id;
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->canManageInventory() && $user->dealer_id === $category->dealer_id;
    }
}
