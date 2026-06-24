<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PromotionScope;
use App\Models\Dealer;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'name' => fake()->words(2, true),
            'scope' => PromotionScope::ALL,
            'target_id' => null,
            'discount_percent' => fake()->numberBetween(5, 30),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(fn () => [
            'scope' => PromotionScope::PRODUCT,
            'target_id' => $productId,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(fn () => [
            'scope' => PromotionScope::CATEGORY,
            'target_id' => $categoryId,
        ]);
    }
}
