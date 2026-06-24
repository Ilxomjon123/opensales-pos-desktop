<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use App\Support\GeoText;

/**
 * Geokoder yoki STIR qidiruvi qaytargan xom hudud nomini berilgan davlat
 * ichidagi kanonik Region/District bilan moslaydi. Mantiq `UzRegions::match`
 * ning DB ekvivalenti: alias → aniq nom → qism (substring) tartibi.
 */
final class RegionMatcher
{
    /**
     * Davlat bo'yicha yuklangan indekslar (so'rov davomida keshlanadi).
     *
     * @var array<int, array{
     *     regionAlias: array<string, Region>,
     *     districtAlias: array<string, District>,
     *     regions: list<array{model: Region, key: string}>,
     *     districtsByRegion: array<int, list<array{model: District, key: string}>>,
     * }>
     */
    private array $cache = [];

    /**
     * @return array{region: Region|null, district: District|null}
     */
    public function match(Country $country, ?string $region, ?string $district): array
    {
        if ($region === null && $district === null) {
            return ['region' => null, 'district' => null];
        }

        $index = $this->index($country);

        $matchedRegion = $region !== null ? $this->matchRegion($index, $region) : null;
        $matchedDistrict = null;

        if ($district !== null) {
            $districtMatch = $this->matchDistrict($index, $district, $matchedRegion);

            if ($districtMatch !== null) {
                // Tuman alias regionni inference qilishi mumkin.
                $matchedRegion ??= $districtMatch->region;
                $matchedDistrict = $districtMatch;
            } elseif ($matchedRegion === null) {
                // Tuman emas, balki shahar/viloyat indikatori bo'lishi mumkin.
                $matchedRegion = $this->matchRegion($index, $district);
            }
        }

        return ['region' => $matchedRegion, 'district' => $matchedDistrict];
    }

    /**
     * @param  array{regionAlias: array<string, Region>, districtAlias: array<string, District>, regions: list<array{model: Region, key: string}>, districtsByRegion: array<int, list<array{model: District, key: string}>>}  $index
     */
    private function matchRegion(array $index, string $region): ?Region
    {
        $key = GeoText::normalize($region);

        if ($key === '') {
            return null;
        }

        if (isset($index['regionAlias'][$key])) {
            return $index['regionAlias'][$key];
        }

        foreach ($index['regions'] as $entry) {
            if ($entry['key'] === $key) {
                return $entry['model'];
            }
        }

        foreach ($index['regions'] as $entry) {
            if (str_contains($entry['key'], $key) || str_contains($key, $entry['key'])) {
                return $entry['model'];
            }
        }

        return null;
    }

    /**
     * @param  array{regionAlias: array<string, Region>, districtAlias: array<string, District>, regions: list<array{model: Region, key: string}>, districtsByRegion: array<int, list<array{model: District, key: string}>>}  $index
     */
    private function matchDistrict(array $index, string $district, ?Region $regionHint): ?District
    {
        $key = GeoText::normalize($district);

        if ($key === '') {
            return null;
        }

        if (isset($index['districtAlias'][$key])) {
            return $index['districtAlias'][$key];
        }

        if ($regionHint !== null) {
            foreach ($index['districtsByRegion'][$regionHint->id] ?? [] as $entry) {
                if ($entry['key'] === $key
                    || str_contains($entry['key'], $key)
                    || str_contains($key, $entry['key'])) {
                    return $entry['model'];
                }
            }
        }

        // Region hint yo'q yoki mos kelmadi — barcha tumanlardan aniq qidiramiz.
        foreach ($index['districtsByRegion'] as $entries) {
            foreach ($entries as $entry) {
                if ($entry['key'] === $key) {
                    return $entry['model'];
                }
            }
        }

        return null;
    }

    /**
     * @return array{regionAlias: array<string, Region>, districtAlias: array<string, District>, regions: list<array{model: Region, key: string}>, districtsByRegion: array<int, list<array{model: District, key: string}>>}
     */
    private function index(Country $country): array
    {
        if (isset($this->cache[$country->id])) {
            return $this->cache[$country->id];
        }

        $regionAlias = [];
        $regions = [];
        $districtAlias = [];
        $districtsByRegion = [];

        $regionModels = Region::query()
            ->with('aliases')
            ->where('country_id', $country->id)
            ->get();

        foreach ($regionModels as $region) {
            $regions[] = ['model' => $region, 'key' => GeoText::normalize($region->name)];

            foreach ($region->aliases as $alias) {
                $regionAlias[$alias->alias] = $region;
            }
        }

        $districtModels = District::query()
            ->with(['aliases', 'region'])
            ->whereHas('region', fn ($q) => $q->where('country_id', $country->id))
            ->get();

        foreach ($districtModels as $district) {
            $districtsByRegion[$district->region_id][] = [
                'model' => $district,
                'key' => GeoText::normalize($district->name),
            ];

            foreach ($district->aliases as $alias) {
                $districtAlias[$alias->alias] = $district;
            }
        }

        return $this->cache[$country->id] = [
            'regionAlias' => $regionAlias,
            'districtAlias' => $districtAlias,
            'regions' => $regions,
            'districtsByRegion' => $districtsByRegion,
        ];
    }
}
