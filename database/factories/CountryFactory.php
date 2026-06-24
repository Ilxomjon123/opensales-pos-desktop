<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
final class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
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
        ];
    }

    public function russia(): static
    {
        return $this->state(fn () => [
            'code' => 'ru',
            'name' => 'Russia',
            'native_name' => 'Россия',
            'flag' => '🇷🇺',
            'phone_prefix' => '+7',
            'phone_digits' => 10,
            'currency' => Currency::RUB->value,
            'default_latitude' => 55.7558,
            'default_longitude' => 37.6173,
            'geo_country_code' => 'ru',
            'bbox' => [41.2, 19.6, 81.9, 180.0],
            'sort' => 1,
        ]);
    }
}
