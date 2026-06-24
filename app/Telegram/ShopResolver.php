<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use SergiX44\Nutgram\Nutgram;

final class ShopResolver
{
    public function __construct(private readonly Dealer $dealer) {}

    public function resolveMember(Nutgram $bot): ?ShopMember
    {
        $telegramId = $bot->userId();

        if ($telegramId === null) {
            return null;
        }

        $member = ShopMember::query()
            ->forTelegram((int) $telegramId)
            ->active()
            ->whereHas('shop', fn ($q) => $q->forDealer($this->dealer->id))
            ->with('shop')
            ->first();

        if ($member !== null) {
            $this->clearBlockedFlag($member);
            $this->touchLastSeen($member);
        }

        return $member;
    }

    /**
     * Foydalanuvchi qayta yozdi — bot bloklash olib tashlangan, flagni tozalaymiz.
     */
    private function clearBlockedFlag(ShopMember $member): void
    {
        if ($member->blocked_at === null) {
            return;
        }

        ShopMember::query()
            ->whereKey($member->getKey())
            ->update(['blocked_at' => null]);

        $member->blocked_at = null;
    }

    private function touchLastSeen(ShopMember $member): void
    {
        $threshold = now()->subMinute();

        if ($member->last_seen_at !== null && $member->last_seen_at->greaterThan($threshold)) {
            return;
        }

        $now = now();

        ShopMember::query()
            ->whereKey($member->getKey())
            ->update(['last_seen_at' => $now]);

        $member->last_seen_at = $now;
    }

    public function resolve(Nutgram $bot): ?Shop
    {
        return $this->resolveMember($bot)?->shop;
    }
}
