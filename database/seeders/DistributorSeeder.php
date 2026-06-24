<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommissionType;
use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Birjada (marketplace) tovar sotadigan botsiz distribyutorlar.
 * Kichik dillerlar shu distribyutorlardan Birja orqali tovar oladi.
 */
final class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->distributors() as $cfg) {
            $this->seedDistributor($cfg);
        }
    }

    /**
     * @param  array{name:string,username:string,phone:string,commission_type:CommissionType,fee_rate:?float,fixed:?int,products:array<string,array<int,array{0:string,1:int,2:int,3:string}>>}  $cfg
     */
    private function seedDistributor(array $cfg): void
    {
        // Botsiz diller (distribyutor) — bot_token null.
        $dealer = Dealer::query()->updateOrCreate(
            ['bot_username' => $cfg['username']],
            [
                'name' => $cfg['name'],
                'bot_token' => null,
                'bot_username' => $cfg['username'],
                'contact_phone' => $cfg['phone'],
                'is_active' => true,
                'sells_on_marketplace' => true,
                'marketplace_commission_type' => $cfg['commission_type'],
                'marketplace_platform_fee_rate' => $cfg['fee_rate'],
                'marketplace_fixed_commission_amount' => $cfg['fixed'],
            ],
        );

        User::query()->updateOrCreate(
            ['username' => $cfg['username']],
            [
                'name' => $cfg['name'],
                'password' => Hash::make('password'),
                'role' => UserRole::DEALER,
                'dealer_id' => $dealer->id,
            ],
        );

        $sort = 0;
        foreach ($cfg['products'] as $catName => $items) {
            $category = ProductCategory::query()->updateOrCreate(
                ['dealer_id' => $dealer->id, 'name' => $catName],
                ['sort_order' => $sort++, 'is_active' => true],
            );

            foreach ($items as $row) {
                [$name, $price, $stock, $unit] = $row;

                Product::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'name' => $name],
                    [
                        'category_id' => $category->id,
                        'price' => $price,
                        'cost_price' => (int) round($price * 0.85),
                        'stock' => $stock,
                        'pack_size' => 1,
                        'unit' => $unit === 'kg' ? ProductUnit::KG : ProductUnit::DONA,
                        'is_active' => true,
                        'visibility' => ProductVisibility::MARKETPLACE_ONLY,
                    ],
                );
            }
        }

        $count = array_sum(array_map('count', $cfg['products']));
        $this->command?->info("→ Distribyutor: {$cfg['name']} — {$count} ta marketplace mahsulot");
    }

    /**
     * @return array<int, array{name:string,username:string,phone:string,commission_type:CommissionType,fee_rate:?float,fixed:?int,products:array<string,array<int,array{0:string,1:int,2:int,3:string}>>}>
     */
    private function distributors(): array
    {
        return [
            [
                'name' => 'MegaDistribution',
                'username' => 'mega_distribution',
                'phone' => '+998901112233',
                'commission_type' => CommissionType::TURNOVER_PERCENTAGE,
                'fee_rate' => 1.5,
                'fixed' => null,
                'products' => [
                    'Ichimliklar' => [
                        ['Coca-Cola 1L (blok)', 78_000, 500, 'dona'],
                        ['Fanta 1L (blok)', 76_000, 420, 'dona'],
                        ['Sprite 1L (blok)', 76_000, 380, 'dona'],
                        ['Suv 1.5L (blok)', 42_000, 800, 'dona'],
                    ],
                    'Shirinliklar' => [
                        ['Snickers (blok)', 95_000, 300, 'dona'],
                        ['Twix (blok)', 92_000, 260, 'dona'],
                        ['Pechenye karton', 120_000, 150, 'dona'],
                    ],
                ],
            ],
            [
                'name' => 'OptomCenter',
                'username' => 'optom_center',
                'phone' => '+998905556677',
                'commission_type' => CommissionType::FIXED_PER_ORDER,
                'fee_rate' => null,
                'fixed' => 5_000,
                'products' => [
                    'Gigiyena' => [
                        ['Shampun 5L', 85_000, 200, 'dona'],
                        ['Sovun karton', 64_000, 350, 'dona'],
                        ['Tish pastasi karton', 110_000, 180, 'dona'],
                    ],
                    'Oziq-ovqat' => [
                        ['Guruch (qop)', 320_000, 120, 'kg'],
                        ['Shakar (qop)', 280_000, 140, 'kg'],
                        ['Yog\' 5L (karton)', 410_000, 90, 'dona'],
                    ],
                ],
            ],
        ];
    }
}
