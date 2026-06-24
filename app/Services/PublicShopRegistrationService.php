<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\OutsideDeliveryZoneException;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PublicShopRegistrationService
{
    public function __construct(
        private readonly GeoResolver $geoResolver,
        private readonly DeliveryZoneService $deliveryZones,
    ) {}

    /**
     * Public bot orqali kelgan TG foydalanuvchi uchun yangi mijoz (manzil)
     * ochadi va uni shop_members ga biriktiradi.
     *
     * Bir TG foydalanuvchi bir nechta manzilga ega bo'lishi mumkin —
     * shop_members.shop_id+telegram_id unique constraint multi-shopni qo'llaydi.
     *
     * Koordinata berilgan bo'lsa reverse-geocode orqali viloyat/tuman
     * aniqlanadi va diller yetkazib berish zonasi tekshiriladi. Zonadan
     * tashqarida bo'lsa — OutsideDeliveryZoneException. Geocode aniqlay
     * olmasa yoki matn-manzil bo'lsa — bloklamasdan ro'yxatga olamiz.
     */
    public function register(
        Dealer $dealer,
        ?int $telegramId,
        string $shopName,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $phone = null,
        ?string $username = null,
    ): ShopMember {
        [$region, $district] = $this->resolveLocation($latitude, $longitude);

        if (! $this->deliveryZones->covers($dealer, $region, $district)) {
            throw OutsideDeliveryZoneException::make($region, $district);
        }

        return DB::transaction(function () use (
            $dealer,
            $telegramId,
            $shopName,
            $address,
            $latitude,
            $longitude,
            $region,
            $district,
            $phone,
            $username,
        ): ShopMember {
            $shop = Shop::query()->create([
                'dealer_id' => $dealer->id,
                'name' => $shopName,
                'phone' => $phone,
                'address' => $address,
                'region' => $region,
                'district' => $district,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'contact_person' => $shopName,
                'balance' => 0,
                'is_active' => true,
            ]);

            return ShopMember::query()->create([
                'shop_id' => $shop->id,
                'telegram_id' => $telegramId,
                'name' => $shopName,
                'username' => $username,
                'is_active' => true,
                'joined_at' => now(),
            ]);
        });
    }

    /**
     * Koordinatadan viloyat/tuman. Geocode xatosi ro'yxatdan o'tishni
     * to'smasin — null qaytaramiz (zona tekshiruvi "ochiq" deb hisoblaydi).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveLocation(?float $latitude, ?float $longitude): array
    {
        if ($latitude === null || $longitude === null) {
            return [null, null];
        }

        try {
            $resolved = $this->geoResolver->reverse($latitude, $longitude);

            return [$resolved['region'], $resolved['district']];
        } catch (\Throwable $e) {
            Log::warning('Public registration reverse-geocode failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'exception' => $e->getMessage(),
            ]);

            return [null, null];
        }
    }
}
