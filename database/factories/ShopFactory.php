<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShopType;
use App\Models\Dealer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
final class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        // Toshkent va viloyatlar atrofidagi tasodifiy koordinatalar.
        // Markaz: 41.3111, 69.2797 (Toshkent). Radius taxminan ±0.25° (~25 km).
        $latitude = fake()->randomFloat(7, 41.0, 41.6);
        $longitude = fake()->randomFloat(7, 69.0, 69.6);

        return [
            'dealer_id' => Dealer::factory(),
            'deliveryman_id' => null,
            'type' => ShopType::TELEGRAM,
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'inn' => fake()->unique()->numerify('#########'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'map_provider' => 'manual',
            'balance' => 0,
            'is_active' => true,
        ];
    }

    public function withoutCoordinates(): static
    {
        return $this->state(fn () => [
            'latitude' => null,
            'longitude' => null,
            'map_provider' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function walkIn(): static
    {
        return $this->state(fn () => [
            'type' => ShopType::WALK_IN,
            'name' => 'Yo\'lakay xaridor',
            'inn' => null,
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'type' => ShopType::INDIVIDUAL,
            'inn' => null,
        ]);
    }
}
