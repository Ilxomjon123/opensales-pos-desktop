<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Offline POS desktop — bitta lokal biznes uchun boshlang'ich ma'lumot:
 * bitta diller, egasi (kassir login), namuna mahsulotlar va POS uchun
 * "yo'l-yo'lakay xaridor" do'koni. Internetsiz, markaziy serversiz ishlaydi.
 */
final class OfflineSeeder extends Seeder
{
    public function run(): void
    {
        $dealer = Dealer::query()->updateOrCreate(
            ['bot_username' => 'pos_local'],
            [
                'name' => 'POS Do\'kon',
                'bot_token' => null,
                'contact_phone' => '+998900000000',
                'currency' => Currency::UZS,
                'is_active' => true,
            ],
        );

        // Kassir/egasi — login: kassa / kassa
        User::query()->updateOrCreate(
            ['username' => 'kassa'],
            [
                'name' => 'Kassir',
                'password' => Hash::make('kassa'),
                'role' => UserRole::DEALER,
                'dealer_id' => $dealer->id,
            ],
        );

        // POS standart mijozi (anonim chakana xaridor)
        Shop::query()->updateOrCreate(
            ['dealer_id' => $dealer->id, 'type' => ShopType::WALK_IN, 'name' => 'Yo\'l-yo\'lakay xaridor'],
            ['is_active' => true, 'balance' => 0],
        );

        $this->seedCatalog($dealer);
    }

    private function seedCatalog(Dealer $dealer): void
    {
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
