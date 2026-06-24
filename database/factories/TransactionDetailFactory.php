<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionDetail>
 */
final class TransactionDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
            'product_name' => $this->faker->words(2, true),
            'qty' => $this->faker->numberBetween(1, 100),
            'unit_cost' => null,
            'stock_before' => 0,
            'stock_after' => 0,
        ];
    }
}
