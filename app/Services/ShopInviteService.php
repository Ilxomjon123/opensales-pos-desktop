<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\InvalidInviteException;
use App\Models\Shop;
use App\Models\ShopInvite;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ShopInviteService
{
    public function createForShop(Shop $shop, User $createdBy): ShopInvite
    {
        return ShopInvite::query()->create([
            'shop_id' => $shop->id,
            'created_by' => $createdBy->id,
            'token' => ShopInvite::generateToken(),
            'expires_at' => now()->addHours(ShopInvite::DEFAULT_TTL_HOURS),
        ]);
    }

    public function findValid(string $token): ?ShopInvite
    {
        return ShopInvite::query()
            ->valid()
            ->where('token', $token)
            ->with('shop')
            ->first();
    }

    /**
     * Token orqali telegram foydalanuvchisini shop_members ga biriktirish.
     * Bir foydalanuvchi bir nechta mijozga a'zo bo'lishi mumkin.
     *
     * Linkga bosilgandan so'ng shu mijoz uchun avtomatik yangi
     * (ishlatilmagan) invite yaratiladi — diller hech qachon "linka
     * yo'q" holatda qolmaydi.
     */
    public function redeem(
        string $token,
        ?int $telegramId = null,
        ?int $customerId = null,
        ?string $name = null,
        ?string $username = null,
    ): ShopMember {
        if ($telegramId === null && $customerId === null) {
            throw InvalidInviteException::notFound();
        }

        return DB::transaction(function () use ($token, $telegramId, $customerId, $name, $username): ShopMember {
            $invite = ShopInvite::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($invite === null) {
                throw InvalidInviteException::notFound();
            }

            if ($invite->used_at !== null) {
                throw InvalidInviteException::alreadyUsed();
            }

            if ($invite->expires_at->isPast()) {
                throw InvalidInviteException::expired();
            }

            $existing = ShopMember::query()
                ->where('shop_id', $invite->shop_id)
                ->where(function ($q) use ($telegramId, $customerId): void {
                    if ($telegramId !== null) {
                        $q->where('telegram_id', $telegramId);
                    }
                    if ($customerId !== null) {
                        $q->orWhere('customer_id', $customerId);
                    }
                })
                ->first();

            $invite->update([
                'used_at' => now(),
                'used_by_telegram_id' => $telegramId,
            ]);

            $this->rotateInviteFor($invite);

            if ($existing !== null) {
                $existing->fill([
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]);
                // Bot vakiliga mijoz akkaunti, yoki mobil vakilga telegram qo'shilsa — to'ldiramiz.
                $existing->telegram_id ??= $telegramId;
                $existing->customer_id ??= $customerId;
                $existing->save();

                return $existing;
            }

            return ShopMember::query()->create([
                'shop_id' => $invite->shop_id,
                'telegram_id' => $telegramId,
                'customer_id' => $customerId,
                'name' => $name,
                'username' => $username,
                'is_active' => true,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);
        });
    }

    /**
     * Ishlatilgan link o'rniga shu shop uchun yangi link yaratadi.
     * Yaratuvchi sifatida — eski linkni yaratgan user ishlatiladi.
     */
    private function rotateInviteFor(ShopInvite $usedInvite): ShopInvite
    {
        return ShopInvite::query()->create([
            'shop_id' => $usedInvite->shop_id,
            'created_by' => $usedInvite->created_by,
            'token' => ShopInvite::generateToken(),
            'expires_at' => now()->addHours(ShopInvite::DEFAULT_TTL_HOURS),
        ]);
    }
}
