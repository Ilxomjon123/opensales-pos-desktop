<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use App\Models\District;
use App\Models\Region;

/**
 * Davlat geo ma'lumotnomasi: viloyat/tuman ro'yxati va nom → FK yechimi.
 * Form (DeliveryZones) va saqlash uchun. So'rov davomida keshlanadi.
 */
final class GeoCatalog
{
    /** @var array<int, array<int, array{name: string, districts: array<int, string>}>> */
    private array $optionsCache = [];

    /** @var array<int, array{regions: array<string, int>, districts: array<int, array<string, int>>}> */
    private array $idCache = [];

    /**
     * Frontend select uchun viloyat → tumanlar (nom bo'yicha).
     *
     * @return array<int, array{name: string, districts: array<int, string>}>
     */
    public function regionOptions(Country $country): array
    {
        if (isset($this->optionsCache[$country->id])) {
            return $this->optionsCache[$country->id];
        }

        $regions = Region::query()
            ->where('country_id', $country->id)
            ->where('is_active', true)
            ->with(['districts' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('name')])
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $options = $regions->map(fn (Region $r): array => [
            'name' => $r->name,
            'districts' => $r->districts->pluck('name')->all(),
        ])->all();

        return $this->optionsCache[$country->id] = $options;
    }

    /**
     * Viloyat/tuman nomidan FK (davlat doirasida aniq moslik).
     *
     * @return array{region_id: int|null, district_id: int|null}
     */
    public function resolveByName(Country $country, ?string $region, ?string $district): array
    {
        if ($region === null) {
            return ['region_id' => null, 'district_id' => null];
        }

        $index = $this->idIndex($country);
        $regionId = $index['regions'][$region] ?? null;

        if ($regionId === null) {
            return ['region_id' => null, 'district_id' => null];
        }

        $districtId = $district !== null
            ? ($index['districts'][$regionId][$district] ?? null)
            : null;

        return ['region_id' => $regionId, 'district_id' => $districtId];
    }

    /**
     * @return array{regions: array<string, int>, districts: array<int, array<string, int>>}
     */
    private function idIndex(Country $country): array
    {
        if (isset($this->idCache[$country->id])) {
            return $this->idCache[$country->id];
        }

        $regions = Region::query()
            ->where('country_id', $country->id)
            ->get(['id', 'name']);

        $regionMap = [];
        foreach ($regions as $region) {
            $regionMap[$region->name] = $region->id;
        }

        $districtMap = [];
        District::query()
            ->whereIn('region_id', array_values($regionMap))
            ->get(['id', 'region_id', 'name'])
            ->each(function (District $d) use (&$districtMap): void {
                $districtMap[$d->region_id][$d->name] = $d->id;
            });

        return $this->idCache[$country->id] = ['regions' => $regionMap, 'districts' => $districtMap];
    }
}
