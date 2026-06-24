<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\Country;
use Illuminate\Database\Seeder;

final class CountrySeeder extends Seeder
{
    /**
     * Davlatlar — idempotent (code bo'yicha). Geo seederlar shu qatorlarga
     * bog'lanadi.
     */
    public function run(): void
    {
        foreach ($this->countries() as $country) {
            Country::query()->updateOrCreate(
                ['code' => $country['code']],
                $country,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function countries(): array
    {
        return [
            [
                'code' => 'uz',
                'name' => 'Uzbekistan',
                'native_name' => "O'zbekiston",
                'flag' => '🇺🇿',
                'phone_prefix' => '+998',
                'phone_digits' => 9,
                'currency' => Currency::UZS->value,
                'default_latitude' => 41.3111,
                'default_longitude' => 69.2797,
                'default_zoom' => 6,
                'geo_country_code' => 'uz',
                'bbox' => [37.0, 55.9, 45.7, 73.2],
                'sort' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'ru',
                'name' => 'Russia',
                'native_name' => 'Россия',
                'flag' => '🇷🇺',
                'phone_prefix' => '+7',
                'phone_digits' => 10,
                'currency' => Currency::RUB->value,
                'default_latitude' => 55.7558,
                'default_longitude' => 37.6173,
                'default_zoom' => 5,
                'geo_country_code' => 'ru',
                'bbox' => [41.2, 19.6, 81.9, 180.0],
                'sort' => 1,
                'is_active' => true,
            ],
        ];
    }
}
