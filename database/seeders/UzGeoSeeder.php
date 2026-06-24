<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use App\Support\GeoText;
use App\Support\UzRegions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * O'zbekiston viloyat/tuman ierarxiyasini va nom variantlarini (aliaslar)
 * `UzRegions` manbasidan bazaga ko'chiradi. Idempotent.
 */
final class UzGeoSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()->where('code', 'uz')->first();

        if ($country === null) {
            $this->call(CountrySeeder::class);
            $country = Country::query()->where('code', 'uz')->firstOrFail();
        }

        DB::transaction(function () use ($country): void {
            $this->seedHierarchy($country->id);
            $this->seedRegionAliases();
            $this->seedDistrictAliases();
        });
    }

    private function seedHierarchy(int $countryId): void
    {
        foreach (UzRegions::all() as $rIndex => $regionData) {
            $region = Region::query()->updateOrCreate(
                ['country_id' => $countryId, 'name' => $regionData['name']],
                ['sort' => $rIndex],
            );

            foreach ($regionData['districts'] as $dIndex => $districtName) {
                District::query()->updateOrCreate(
                    ['region_id' => $region->id, 'name' => $districtName],
                    ['sort' => $dIndex],
                );
            }
        }
    }

    private function seedRegionAliases(): void
    {
        foreach (UzRegions::regionAliasDefinitions() as $canonical => $aliases) {
            $region = Region::query()->where('name', $canonical)->first();

            if ($region === null) {
                continue;
            }

            // Kanonik nomning o'zi ham normallashtirilgan holda alias bo'lib qo'shiladi.
            $this->attachAliases($region, array_merge([$canonical], $aliases));
        }
    }

    private function seedDistrictAliases(): void
    {
        foreach (UzRegions::districtAliasDefinitions() as [$regionName, $districtName, $aliases]) {
            $district = District::query()
                ->whereHas('region', fn ($q) => $q->where('name', $regionName))
                ->where('name', $districtName)
                ->first();

            if ($district === null) {
                continue;
            }

            $this->attachAliases($district, array_merge([$districtName], $aliases));
        }
    }

    /**
     * @param  Region|District  $model
     * @param  list<string>  $aliases
     */
    private function attachAliases($model, array $aliases): void
    {
        foreach ($aliases as $alias) {
            $normalized = GeoText::normalize($alias);

            if ($normalized === '') {
                continue;
            }

            $model->aliases()->firstOrCreate(['alias' => $normalized]);
        }
    }
}
