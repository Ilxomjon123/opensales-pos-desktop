<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShopVisit;
use App\Models\User;

final class ShopVisitPolicy
{
    /**
     * Vizitni tahrirlash/o'chirish faqat qayd etilgandan keyingi shu oyna ichida.
     */
    public const EDIT_WINDOW_HOURS = 4;

    public function update(User $user, ShopVisit $visit): bool
    {
        return $this->canModify($user, $visit);
    }

    public function delete(User $user, ShopVisit $visit): bool
    {
        return $this->canModify($user, $visit);
    }

    /**
     * Faqat: diller egasi (super admin impersonation orqali ham diller bo'lib
     * keladi) yoki vizit muallifi — va faqat 4 soatlik oyna ichida.
     */
    private function canModify(User $user, ShopVisit $visit): bool
    {
        if ($user->dealer_id !== $visit->dealer_id) {
            return false;
        }

        if (! $this->withinEditWindow($visit)) {
            return false;
        }

        return $user->isDealer() || $visit->user_id === $user->id;
    }

    private function withinEditWindow(ShopVisit $visit): bool
    {
        if ($visit->created_at === null) {
            return false;
        }

        return $visit->created_at->copy()->addHours(self::EDIT_WINDOW_HOURS)->isFuture();
    }
}
