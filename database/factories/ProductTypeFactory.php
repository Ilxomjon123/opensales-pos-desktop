<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductType>
 */
final class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'price' => fake()->numberBetween(1_000, 500_000),
            'stock' => fake()->numberBetween(0, 500),
            'min_stock' => null,
            'pack_size' => 1,
            'bulk_only' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function withPack(int $size = 12): static
    {
        return $this->state(fn () => ['pack_size' => $size]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
