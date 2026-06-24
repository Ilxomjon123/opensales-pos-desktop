<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use App\Support\GeoText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rossiya federal subyektlari + shahar/tumanlari. Ma'lumot
 * `database/data/ru_geo.json` faylidan o'qiladi (manba: arbaev/russia-cities +
 * federal shahar okruglari). Yangi hudud qo'shish = JSON ni tahrirlash, kodga
 * tegmaydi. Idempotent.
 */
final class RuGeoSeeder extends Seeder
{
    private const DATA_PATH = 'database/data/ru_geo.json';

    public function run(): void
    {
        $country = Country::query()->where('code', 'ru')->first();

        if ($country === null) {
            $this->call(CountrySeeder::class);
            $country = Country::query()->where('code', 'ru')->firstOrFail();
        }

        foreach ($this->load() as $index => $regionData) {
            DB::transaction(function () use ($country, $index, $regionData): void {
                $region = Region::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $regionData['name']],
                    ['sort' => $index],
                );

                $regionAliases = array_merge([$regionData['name']], $regionData['aliases'] ?? []);
                $this->attachAliases($region, $regionAliases);

                foreach ($regionData['districts'] ?? [] as $dIndex => $districtName) {
                    $district = District::query()->updateOrCreate(
                        ['region_id' => $region->id, 'name' => $districtName],
                        ['sort' => $dIndex],
                    );

                    $this->attachAliases($district, [$districtName]);
                }
            });
        }
    }

    /**
     * @return list<array{name: string, aliases?: list<string>, center?: array{0: float, 1: float}|null, districts?: list<string>}>
     */
    private function load(): array
    {
        $path = base_path(self::DATA_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Geo ma'lumot fayli topilmadi: ".self::DATA_PATH);
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException('Geo ma\'lumot fayli buzuq: '.self::DATA_PATH);
        }

        /** @var list<array{name: string, aliases?: list<string>, center?: array{0: float, 1: float}|null, districts?: list<string>}> $data */
        return $data;
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
