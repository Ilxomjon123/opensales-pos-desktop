<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
final class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => $this->faker->unique()->city().' viloyati',
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
