<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(1_000, 100_000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'price' => $price,
            'qty' => fake()->numberBetween(1, 20),
            'unit' => ProductUnit::DONA,
            'pack_size' => 1,
            'pack_qty' => null,
        ];
    }
}
