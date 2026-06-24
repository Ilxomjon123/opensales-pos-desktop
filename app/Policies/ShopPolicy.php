<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Services\ImpersonationService;

final class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageDealer();
    }

    public function view(User $user, Shop $shop): bool
    {
        return $user->canManageDealer() && $user->dealer_id === $shop->dealer_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageDealer();
    }

    /**
     * Diller (egasi) o'z dealerining istalgan mijozini tahrirlay oladi.
     * Yetkazib beruvchi o'ziga biriktirilgan mijozni tahrirlay oladi —
     * mijoz vakili (member) biriktirilgan bo'lsa ham. Super admin
     * impersonation orqali kirsa — doim ruxsat.
     */
    public function update(User $user, Shop $shop): bool
    {
        if ($user->dealer_id !== $shop->dealer_id) {
            return false;
        }

        if ($this->isImpersonatedBySuperAdmin()) {
            return true;
        }

        if ($user->isDealer()) {
            return true;
        }

        return $user->isDeliveryman() && $shop->deliveryman_id === $user->id;
    }

    private function isImpersonatedBySuperAdmin(): bool
    {
        $impersonation = app(ImpersonationService::class);

        if (! $impersonation->isImpersonating()) {
            return false;
        }

        $impersonatorId = $impersonation->impersonatorId();

        if ($impersonatorId === null) {
            return false;
        }

        $impersonator = User::query()->find($impersonatorId);

        return $impersonator !== null && $impersonator->isSuperAdmin();
    }

    public function delete(User $user, Shop $shop): bool
    {
        if ($user->dealer_id !== $shop->dealer_id) {
            return false;
        }

        if ($this->isImpersonatedBySuperAdmin()) {
            return true;
        }

        return $user->isDealer();
    }

    /**
     * Rasmni diller yoki biriktirilgan yetkazib beruvchi istalgan paytda
     * almashtira oladi — mijoz vakili biriktirilgan bo'lsa ham.
     */
    public function updatePhoto(User $user, Shop $shop): bool
    {
        if ($user->dealer_id !== $shop->dealer_id) {
            return false;
        }

        if ($user->isDealer()) {
            return true;
        }

        return $user->isDeliveryman() && $shop->deliveryman_id === $user->id;
    }

    /**
     * Diller o'z dealerining istalgan mijozini taklif qila oladi.
     * Yetkazib beruvchi ham dealerning istalgan mijozini taklif qila oladi —
     * o'zi yaratmagan yoki o'ziga biriktirilmagan bo'lsa ham.
     */
    public function invite(User $user, Shop $shop): bool
    {
        if ($user->dealer_id !== $shop->dealer_id) {
            return false;
        }

        return $user->isDealer() || $user->isDeliveryman();
    }

    /**
     * Vizitni diller yoki dealerning istalgan yetkazib beruvchisi qayd eta oladi.
     */
    public function recordVisit(User $user, Shop $shop): bool
    {
        if ($user->dealer_id !== $shop->dealer_id) {
            return false;
        }

        return $user->isDealer() || $user->isDeliveryman();
    }
}
