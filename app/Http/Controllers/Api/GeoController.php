<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\RegionResource;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Geo ma'lumotnomasi (davlat → viloyat → tuman). Web (LocationPicker,
 * DeliveryZones) va mobil uchun yagona ochiq manba. Faqat o'qish.
 */
final class GeoController extends Controller
{
    public function countries(): AnonymousResourceCollection
    {
        $countries = Country::query()->active()->ordered()->get();

        return CountryResource::collection($countries);
    }

    public function regions(Country $country): AnonymousResourceCollection
    {
        $regions = $country->regions()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return RegionResource::collection($regions);
    }

    public function districts(Region $region): AnonymousResourceCollection
    {
        $districts = $region->districts()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return DistrictResource::collection($districts);
    }
}
