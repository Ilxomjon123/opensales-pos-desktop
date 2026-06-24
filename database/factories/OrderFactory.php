<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $dealer = Dealer::factory()->create();

        return [
            'shop_id' => Shop::factory()->for($dealer),
            'dealer_id' => $dealer->id,
            'status' => OrderStatus::PENDING,
            'total' => fake()->numberBetween(10_000, 1_000_000),
            'note' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::CONFIRMED]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::DELIVERED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::CANCELLED]);
    }
}
