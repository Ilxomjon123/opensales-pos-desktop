<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
final class DistrictFactory extends Factory
{
    protected $model = District::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => $this->faker->unique()->city().' tumani',
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
