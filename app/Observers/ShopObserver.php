<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Country;
use App\Models\Shop;
use App\Services\DirectoryShopService;
use App\Services\Geo\RegionMatcher;

final class ShopObserver
{
    public function __construct(
        private readonly DirectoryShopService $directory,
        private readonly RegionMatcher $matcher,
    ) {}

    /**
     * Region/district matni o'zgarsa (yoki FK hali bo'sh bo'lsa) — uni diller
     * davlati bo'yicha country_id/region_id/district_id ga moslaymiz. Shu sabab
     * yangi yozuvlar darhol normallashgan holatda saqlanadi.
     */
    public function saving(Shop $shop): void
    {
        $needsResolve = $shop->region !== null
            && ($shop->region_id === null || $shop->isDirty('region') || $shop->isDirty('district'));

        if (! $needsResolve) {
            return;
        }

        $country = $this->resolveCountry($shop);

        if ($country === null) {
            return;
        }

        $matched = $this->matcher->match($country, $shop->region, $shop->district);

        $shop->country_id = $country->id;
        $shop->region_id = $matched['region']?->id;
        $shop->district_id = $matched['district']?->id;
    }

    /**
     * Yangi shop spravochnik bilan moslanadi: mavjud yozuvga bog'lanadi
     * yoki yangi spravochnik yozuvi sifatida qo'shiladi.
     */
    public function created(Shop $shop): void
    {
        $this->directory->syncFromShop($shop);
    }

    private function resolveCountry(Shop $shop): ?Country
    {
        $dealerCountryId = $shop->dealer?->country_id;

        if ($dealerCountryId !== null) {
            return Country::query()->find($dealerCountryId);
        }

        return Country::query()->where('code', 'uz')->first();
    }
}
