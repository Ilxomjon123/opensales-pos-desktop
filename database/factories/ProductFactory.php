<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use App\Models\Dealer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'category_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(1_000, 500_000),
            'stock' => fake()->numberBetween(0, 500),
            'pack_size' => 1,
            'unit' => ProductUnit::DONA,
            'is_active' => true,
            'visibility' => ProductVisibility::BOT_ONLY,
        ];
    }

    public function visibility(ProductVisibility $visibility): static
    {
        return $this->state(fn () => ['visibility' => $visibility]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function withPack(int $size = 12): static
    {
        return $this->state(fn () => ['pack_size' => $size]);
    }
}
