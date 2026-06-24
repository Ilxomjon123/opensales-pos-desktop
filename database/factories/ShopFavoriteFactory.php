<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopFavorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopFavorite>
 */
final class ShopFavoriteFactory extends Factory
{
    protected $model = ShopFavorite::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
