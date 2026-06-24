<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DirectoryShop;
use App\Services\DirectoryShopService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectoryShop>
 */
final class DirectoryShopFactory extends Factory
{
    protected $model = DirectoryShop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = fake()->numerify('+998 ## ###-##-##');

        return [
            'name' => fake()->company(),
            'legal_name' => null,
            'inn' => fake()->unique()->numerify('#########'),
            'phone' => $phone,
            'phone_normalized' => DirectoryShopService::normalizePhone($phone),
            'contact_person' => fake()->name(),
            'address' => fake()->address(),
            'landmark' => null,
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
            'latitude' => fake()->randomFloat(7, 41.0, 41.6),
            'longitude' => fake()->randomFloat(7, 69.0, 69.6),
            'photo' => null,
            'source' => 'manual',
        ];
    }
}
