<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Dealer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'user_id' => User::factory(),
            'actor_name' => $this->faker->name(),
            'type' => TransactionType::STOCK_IN,
            'note' => null,
        ];
    }

    public function stockIn(): self
    {
        return $this->state(fn (): array => ['type' => TransactionType::STOCK_IN]);
    }
}
