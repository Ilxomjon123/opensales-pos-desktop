<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
final class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        $dealer = Dealer::factory()->create();

        return [
            'supplier_id' => Supplier::factory()->for($dealer),
            'dealer_id' => $dealer->id,
            'transaction_id' => null,
            'amount' => fake()->numberBetween(50_000, 1_000_000),
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
