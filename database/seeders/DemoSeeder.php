<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $dealers = [
            [
                'name' => 'Coca-Cola Toshkent',
                'username' => 'coca',
                'bot_username' => 'cocacola_toshkent_bot',
                'products' => [
                    ['name' => 'Coca-Cola 1L', 'price' => 12_000, 'stock' => 200, 'unit' => 'dona', 'description' => 'Klassik ta\'m, 1 litr'],
                    ['name' => 'Coca-Cola 0.5L', 'price' => 7_000, 'stock' => 350, 'unit' => 'dona', 'description' => 'Klassik ta\'m, 0.5 litr'],
                    ['name' => 'Coca-Cola 2L', 'price' => 18_000, 'stock' => 150, 'unit' => 'dona'],
                    ['name' => 'Fanta 1L', 'price' => 11_000, 'stock' => 180, 'unit' => 'dona', 'description' => 'Apelsin ta\'mli'],
                    ['name' => 'Fanta 0.5L', 'price' => 6_500, 'stock' => 300, 'unit' => 'dona'],
                    ['name' => 'Sprite 1L', 'price' => 11_000, 'stock' => 160, 'unit' => 'dona', 'description' => 'Limon-laym'],
                    ['name' => 'Sprite 0.5L', 'price' => 6_500, 'stock' => 280, 'unit' => 'dona'],
                    ['name' => 'BonAqua 0.5L', 'price' => 3_000, 'stock' => 500, 'unit' => 'dona', 'description' => 'Toza ichimlik suvi'],
                    ['name' => 'BonAqua 1.5L', 'price' => 5_000, 'stock' => 400, 'unit' => 'dona'],
                    ['name' => 'Coca-Cola Zero 0.5L', 'price' => 8_000, 'stock' => 120, 'unit' => 'dona', 'description' => 'Shakarsiz'],
                    ['name' => 'Fuse Tea 1L', 'price' => 10_000, 'stock' => 90, 'unit' => 'dona', 'description' => 'Shaftoli ta\'mli choy'],
                    ['name' => 'Coca-Cola Cherry 0.5L', 'price' => 9_000, 'stock' => 80, 'unit' => 'dona'],
                ],
            ],
            [
                'name' => 'Anvar Non',
                'username' => 'anvar',
                'bot_username' => 'anvar_non_bot',
                'products' => [
                    ['name' => 'Obi-non', 'price' => 3_500, 'stock' => 100, 'unit' => 'dona', 'description' => 'Tandirda pishirilgan'],
                    ['name' => 'Patir non', 'price' => 5_000, 'stock' => 80, 'unit' => 'dona'],
                    ['name' => 'Katlama', 'price' => 6_000, 'stock' => 60, 'unit' => 'dona', 'description' => 'Yog\'li qatlamali non'],
                    ['name' => 'Somsa go\'shtli', 'price' => 8_000, 'stock' => 150, 'unit' => 'dona', 'description' => 'Mol go\'shti bilan'],
                    ['name' => 'Somsa kartoshkali', 'price' => 6_000, 'stock' => 100, 'unit' => 'dona'],
                    ['name' => 'Qozon kabob somsa', 'price' => 10_000, 'stock' => 50, 'unit' => 'dona', 'description' => 'Maxsus retsept'],
                    ['name' => 'Chak-chak', 'price' => 15_000, 'stock' => 40, 'unit' => 'kg', 'description' => 'Asalli shirinlik'],
                    ['name' => 'Manti', 'price' => 5_000, 'stock' => 200, 'unit' => 'dona', 'description' => 'Go\'shtli manti'],
                    ['name' => 'Pishloqli non', 'price' => 7_000, 'stock' => 70, 'unit' => 'dona'],
                    ['name' => 'Baroq non', 'price' => 4_000, 'stock' => 90, 'unit' => 'dona', 'description' => 'Yumshoq baroq non'],
                ],
            ],
            [
                'name' => 'Shams Sut',
                'username' => 'shams',
                'bot_username' => 'shams_sut_bot',
                'products' => [
                    ['name' => 'Sut 1L (2.5%)', 'price' => 10_000, 'stock' => 300, 'unit' => 'dona', 'description' => 'Pasterizatsiya qilingan'],
                    ['name' => 'Sut 0.5L (2.5%)', 'price' => 6_000, 'stock' => 250, 'unit' => 'dona'],
                    ['name' => 'Kefir 1L', 'price' => 11_000, 'stock' => 180, 'unit' => 'dona'],
                    ['name' => 'Qatiq 1L', 'price' => 12_000, 'stock' => 150, 'unit' => 'dona', 'description' => 'Tabiiy qatiq'],
                    ['name' => 'Smetana 200g', 'price' => 7_000, 'stock' => 120, 'unit' => 'dona', 'description' => '20% yog\'lilik'],
                    ['name' => 'Tvorog 300g', 'price' => 12_000, 'stock' => 100, 'unit' => 'dona'],
                    ['name' => 'Sariyog\' 200g', 'price' => 18_000, 'stock' => 80, 'unit' => 'dona', 'description' => '82% yog\'lilik'],
                    ['name' => 'Yogurt 200g', 'price' => 5_000, 'stock' => 400, 'unit' => 'dona', 'description' => 'Qulupnayli'],
                    ['name' => 'Suzma 500g', 'price' => 15_000, 'stock' => 90, 'unit' => 'dona'],
                    ['name' => 'Pishloq 1kg', 'price' => 65_000, 'stock' => 50, 'unit' => 'kg', 'description' => 'Qattiq pishloq'],
                    ['name' => 'Kaymok 500g', 'price' => 25_000, 'stock' => 60, 'unit' => 'dona', 'description' => 'Tabiiy kaymok'],
                    ['name' => 'Sut 3L (2.5%)', 'price' => 28_000, 'stock' => 100, 'unit' => 'dona'],
                ],
            ],
        ];

        foreach ($dealers as $data) {
            $dealer = Dealer::query()->updateOrCreate(
                ['bot_username' => $data['bot_username']],
                [
                    'name' => $data['name'],
                    'bot_token' => fake()->numerify('##########:').fake()->regexify('[A-Za-z0-9]{35}'),
                    'is_active' => true,
                ],
            );

            User::query()->updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::DEALER,
                    'dealer_id' => $dealer->id,
                ],
            );

            foreach ($data['products'] as $product) {
                Product::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'name' => $product['name']],
                    [
                        ...$product,
                        'dealer_id' => $dealer->id,
                        'is_active' => true,
                    ],
                );
            }

            $this->command->info("  ✓ {$data['name']} — {$data['username']} / password — ".count($data['products']).' ta mahsulot');
        }
    }
}
