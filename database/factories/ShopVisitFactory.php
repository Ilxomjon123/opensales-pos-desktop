<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopVisit>
 */
final class ShopVisitFactory extends Factory
{
    protected $model = ShopVisit::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'dealer_id' => fn (array $attrs) => Shop::query()->find($attrs['shop_id'])?->dealer_id ?? Shop::factory(),
            'user_id' => User::factory(),
            'note' => fake()->boolean(70) ? fake()->sentence() : null,
            'visited_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
