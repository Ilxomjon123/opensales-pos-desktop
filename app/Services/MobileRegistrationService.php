<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BotVisibility;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mijoz o'z-ro'yxati: joylashuvni qoplaydigan HAR BIR ochiq dillerda
 * avtomatik Shop + ShopMember yaratadi (fan-out) va customer ga bog'laydi.
 * Zona belgilamagan ochiq diller ham mos hisoblanadi (covers()=true).
 */
final class MobileRegistrationService
{
    public function __construct(
        private readonly GeoResolver $geoResolver,
        private readonly DeliveryZoneService $deliveryZones,
    ) {}

    /**
     * @return Collection<int, ShopMember> Yaratilgan/mavjud vakil yozuvlari (har diller uchun bitta)
     */
    public function register(
        Customer $customer,
        string $shopName,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $region = null,
        ?string $district = null,
    ): Collection {
        // Qo'lda viloyat/tuman berilsa — o'shani ishlatamiz, aks holda
        // koordinatadan aniqlaymiz (xaritadan tanlangan nuqta).
        if ($region === null || $region === '') {
            [$region, $district] = $this->resolveLocation($latitude, $longitude);
        }

        $dealers = Dealer::query()
            ->where('is_active', true)
            ->where('visibility', BotVisibility::PUBLIC)
            ->get()
            ->filter(fn (Dealer $dealer) => $this->deliveryZones->covers($dealer, $region, $district));

        return DB::transaction(function () use (
            $dealers, $customer, $shopName, $address, $latitude, $longitude, $region, $district
        ): Collection {
            return $dealers->map(fn (Dealer $dealer): ShopMember => $this->createMembership(
                $dealer, $customer, $shopName, $address, $latitude, $longitude, $region, $district,
            ))->values();
        });
    }

    private function createMembership(
        Dealer $dealer,
        Customer $customer,
        string $shopName,
        string $address,
        ?float $latitude,
        ?float $longitude,
        ?string $region,
        ?string $district,
    ): ShopMember {
        // Har bir ro'yxat = yangi joy (do'kon). Bir mijoz bitta dillerda
        // bir nechta joyga ega bo'lishi mumkin.
        $shop = Shop::query()->create([
            'dealer_id' => $dealer->id,
            'name' => $shopName,
            'phone' => $customer->phone,
            'address' => $address,
            'region' => $region,
            'district' => $district,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'contact_person' => $customer->name ?? $customer->phone,
            'balance' => 0,
            'is_active' => true,
        ]);

        return ShopMember::query()->create([
            'shop_id' => $shop->id,
            'telegram_id' => null,
            'customer_id' => $customer->id,
            'name' => $shopName,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /**
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
            Log::warning('Mobile registration reverse-geocode failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'exception' => $e->getMessage(),
            ]);

            return [null, null];
        }
    }
}
