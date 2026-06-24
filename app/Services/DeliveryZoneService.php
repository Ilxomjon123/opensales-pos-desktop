<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Country;
use App\Models\Dealer;
use App\Models\DealerDeliveryZone;
use App\Services\Geo\GeoCatalog;

/**
 * Diller yetkazib berish hududlarini boshqarish va tekshirish.
 *
 * Default ochiq: diller bitta ham zona belgilamasa — hamma joyga yetkazadi.
 * district NULL bo'lgan zona qatori = butun viloyat (barcha tumanlar).
 */
final class DeliveryZoneService
{
    public function __construct(private readonly GeoCatalog $catalog) {}

    /**
     * Diller davlati (yo'q bo'lsa — O'zbekiston; u ham seed qilinmagan bo'lsa null).
     */
    public function countryFor(Dealer $dealer): ?Country
    {
        return $dealer->country
            ?? Country::query()->where('code', 'uz')->first();
    }

    /**
     * Diller berilgan hududga yetkazadimi?
     *
     * - Zona yo'q                    → true (ochiq)
     * - region null (aniqlanmagan)   → true (bloklamaymiz)
     * - (region, NULL) qatori bor    → true (butun viloyat)
     * - (region, district) qatori    → true
     * - aks holda                    → false
     */
    public function covers(Dealer $dealer, ?string $region, ?string $district): bool
    {
        if (! $dealer->deliveryZones()->exists()) {
            return true;
        }

        if ($region === null || $region === '') {
            return true;
        }

        return $dealer->deliveryZones()
            ->where('region', $region)
            ->where(function ($query) use ($district): void {
                $query->whereNull('district');

                if ($district !== null && $district !== '') {
                    $query->orWhere('district', $district);
                }
            })
            ->exists();
    }

    /**
     * Diller zonalarini frontend uchun guruhlangan ko'rinishda qaytaradi.
     * Butun viloyat tanlangan bo'lsa — barcha tumanlar ro'yxatga kiritiladi.
     *
     * @return array<int, array{region: string, districts: array<int, string>, whole_region: bool}>
     */
    public function selectionForDealer(Dealer $dealer): array
    {
        $zones = $dealer->deliveryZones()->get(['region', 'district']);

        if ($zones->isEmpty()) {
            return [];
        }

        $country = $this->countryFor($dealer);
        $allRegions = $country !== null
            ? collect($this->catalog->regionOptions($country))->keyBy('name')
            : collect();

        return $zones
            ->groupBy('region')
            ->map(function ($group, string $region) use ($allRegions): array {
                $wholeRegion = $group->contains(fn (DealerDeliveryZone $z) => $z->district === null);

                $districts = $wholeRegion
                    ? ($allRegions[$region]['districts'] ?? [])
                    : $group->pluck('district')->filter()->values()->all();

                return [
                    'region' => $region,
                    'districts' => array_values($districts),
                    'whole_region' => $wholeRegion,
                ];
            })
            ->values()
            ->all();
    }
}
