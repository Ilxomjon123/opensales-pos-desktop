<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'contact_person' => fake()->name(),
            'address' => fake()->address(),
            'note' => null,
            'balance' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
