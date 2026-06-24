<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $dealer = Dealer::factory()->create();

        return [
            'shop_id' => Shop::factory()->for($dealer),
            'dealer_id' => $dealer->id,
            'amount' => fake()->numberBetween(10_000, 500_000),
            'type' => fake()->randomElement(PaymentType::cases()),
            'method' => PaymentMethod::CASH,
            'cardholder_name' => null,
            'note' => null,
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => ['type' => PaymentType::CREDIT]);
    }

    public function debit(): static
    {
        return $this->state(fn () => ['type' => PaymentType::DEBIT]);
    }
}
