<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\ShopMember;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bot ↔ mobil mijoz akkauntini ulash. Botda foydalanuvchi kod oladi,
 * mobil ilovada (allaqachon telefon bilan kirgan) shu kodni kiritadi —
 * natijada o'sha telegram foydalanuvchining barcha vakil yozuvlari shu
 * Customer ga ko'chiriladi (birlashtirish).
 */
final class MobileAppLinkService
{
    private const TTL_SECONDS = 600;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Bot tomonda: telegram foydalanuvchi uchun 6 xonali ulash kodi yaratadi.
     */
    public function issueCode(int $telegramId): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->cache->put($this->key($code), $telegramId, self::TTL_SECONDS);

        return $code;
    }

    /**
     * Mobil tomonda: kodni mijoz akkauntiga ulaydi. Telegram_id ning barcha
     * vakil yozuvlari $customer ga o'tkaziladi. Eski (telefonsiz, backfill)
     * customer bo'sh qolsa o'chiriladi. Muvaffaqiyatda true.
     */
    public function consume(string $code, Customer $customer): bool
    {
        $telegramId = $this->cache->get($this->key($code));

        if ($telegramId === null) {
            return false;
        }

        $this->cache->forget($this->key($code));
        $this->link($customer, (int) $telegramId);

        return true;
    }

    /**
     * Telegram_id ning barcha vakil yozuvlarini $customer ga bog'laydi
     * (botdagi diller/shoplar mobil ilovada ham chiqishi uchun).
     * Telegram orqali kirishda ham shu ishlatiladi.
     */
    public function link(Customer $customer, int $telegramId): void
    {
        DB::transaction(function () use ($telegramId, $customer): void {
            /** @var Collection<int, ShopMember> $tgMembers */
            $tgMembers = ShopMember::query()
                ->where('telegram_id', $telegramId)
                ->get();

            $orphanCustomerIds = $tgMembers
                ->pluck('customer_id')
                ->filter(fn ($id) => $id !== null && $id !== $customer->id)
                ->unique();

            foreach ($tgMembers as $tgMember) {
                if ($tgMember->customer_id === $customer->id) {
                    $tgMember->forceFill(['app_linked_at' => now()])->save();

                    continue;
                }

                // Shu do'konda customer'ning mavjud vakili bo'lsa — bitta yozuvga
                // birlashtiramiz (telegram_id ni unga ko'chirib, dublikatni o'chiramiz).
                // Unique(shop_id, customer_id) buzilmasligi uchun shart.
                $existing = ShopMember::query()
                    ->where('shop_id', $tgMember->shop_id)
                    ->where('customer_id', $customer->id)
                    ->where('id', '!=', $tgMember->id)
                    ->first();

                if ($existing !== null) {
                    $tid = $tgMember->telegram_id;
                    // Avval dublikatni o'chiramiz — unique(shop_id, telegram_id) buzilmasin.
                    $tgMember->delete();

                    $existing->forceFill([
                        'telegram_id' => $tid,
                        'app_linked_at' => now(),
                        'is_active' => true,
                    ])->save();

                    continue;
                }

                $tgMember->forceFill([
                    'customer_id' => $customer->id,
                    'app_linked_at' => now(),
                ])->save();
            }

            // Bo'shab qolgan telefonsiz (backfill) customer larni tozalaymiz.
            Customer::query()
                ->whereIn('id', $orphanCustomerIds)
                ->whereNull('phone')
                ->whereDoesntHave('shopMembers')
                ->delete();
        });
    }

    private function key(string $code): string
    {
        return "mobile_link:{$code}";
    }
}
