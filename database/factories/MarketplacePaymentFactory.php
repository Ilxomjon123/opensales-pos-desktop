<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\MarketplacePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketplacePayment>
 */
final class MarketplacePaymentFactory extends Factory
{
    protected $model = MarketplacePayment::class;

    public function definition(): array
    {
        return [
            'seller_dealer_id' => Dealer::factory(),
            'buyer_dealer_id' => Dealer::factory(),
            'order_id' => null,
            'amount' => fake()->numberBetween(10_000, 5_000_000),
            'type' => PaymentType::DEBIT,
            'method' => PaymentMethod::CASH,
            'note' => null,
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => ['type' => PaymentType::CREDIT]);
    }
}
