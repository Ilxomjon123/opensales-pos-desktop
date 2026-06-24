<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopMember>
 */
final class ShopMemberFactory extends Factory
{
    protected $model = ShopMember::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'telegram_id' => fake()->unique()->numberBetween(100_000_000, 999_999_999),
            'name' => fake()->name(),
            'username' => fake()->optional()->userName(),
            'is_active' => true,
            'joined_at' => now(),
        ];
    }
}
