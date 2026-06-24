<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PosShiftStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\PosShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
final class PosShiftFactory extends Factory
{
    protected $model = PosShift::class;

    public function definition(): array
    {
        $dealer = Dealer::factory()->create();

        return [
            'dealer_id' => $dealer->id,
            'cashier_user_id' => User::factory()->state([
                'role' => UserRole::CASHIER,
                'dealer_id' => $dealer->id,
            ]),
            'status' => PosShiftStatus::OPEN,
            'opened_at' => now(),
            'closed_at' => null,
            'opening_cash' => fake()->numberBetween(0, 500_000),
            'closing_cash' => null,
            'expected_cash' => null,
            'cash_diff' => null,
            'total_sales' => 0,
            'total_cash' => 0,
            'total_card' => 0,
            'total_debt' => 0,
            'sales_count' => 0,
            'opening_note' => null,
            'closing_note' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => PosShiftStatus::CLOSED,
            'closed_at' => now(),
            'closing_cash' => fake()->numberBetween(0, 1_000_000),
            'expected_cash' => fake()->numberBetween(0, 1_000_000),
            'cash_diff' => 0,
        ]);
    }
}
