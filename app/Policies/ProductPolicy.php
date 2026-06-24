<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageDealer();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->canManageDealer() && $user->dealer_id === $product->dealer_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    /**
     * Bulk update (ommaviy narx o'zgartirish) — faqat dealer.
     */
    public function updateAny(User $user): bool
    {
        return $user->isDealer();
    }

    /**
     * Mahsulotlar tartibini o'zgartirish (mini app ko'rinishi uchun) — dealer va skladchi.
     */
    public function reorder(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isDealer() && $user->dealer_id === $product->dealer_id;
    }

    /**
     * Faollik tugmasi (skladchiga ham ruxsat — tovar qoldiq tugaganda nofaol qiladi).
     */
    public function toggleActive(User $user, Product $product): bool
    {
        return $user->canManageInventory() && $user->dealer_id === $product->dealer_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isDealer() && $user->dealer_id === $product->dealer_id;
    }
}
