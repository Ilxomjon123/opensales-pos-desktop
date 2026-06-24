<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Namuna mahsulot katalogi (kategoriya + mahsulot). Alohida seeder —
 * demo ma'lumotni mustaqil qayta yuklash/o'chirish uchun.
 */
final class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $dealer = Dealer::query()->firstWhere('bot_username', 'pos_local');

        if ($dealer === null) {
            return;
        }

        $catalog = [
            'Ichimliklar' => [
                ['Coca-Cola 1L', 12_000, 200, 'dona'],
                ['Fanta 1L', 12_000, 150, 'dona'],
                ['Suv 1.5L', 4_000, 300, 'dona'],
                ['Choy 250g', 18_000, 80, 'dona'],
            ],
            'Shirinliklar' => [
                ['Snickers', 6_000, 120, 'dona'],
                ['Twix', 6_000, 100, 'dona'],
                ['Pechenye', 9_000, 90, 'dona'],
            ],
            'Oziq-ovqat' => [
                ['Non', 2_500, 60, 'dona'],
                ['Guruch', 14_000, 50, 'kg'],
                ['Shakar', 11_000, 70, 'kg'],
            ],
        ];

        $sort = 0;
        foreach ($catalog as $catName => $items) {
            $category = ProductCategory::query()->updateOrCreate(
                ['dealer_id' => $dealer->id, 'name' => $catName],
                ['sort_order' => $sort++, 'is_active' => true],
            );

            foreach ($items as [$name, $price, $stock, $unit]) {
                Product::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'name' => $name],
                    [
                        'category_id' => $category->id,
                        'price' => $price,
                        'cost_price' => (int) round($price * 0.8),
                        'stock' => $stock,
                        'pack_size' => 1,
                        'unit' => $unit === 'kg' ? ProductUnit::KG : ProductUnit::DONA,
                        'is_active' => true,
                        'visibility' => ProductVisibility::BOT_ONLY,
                    ],
                );
            }
        }
    }
}
