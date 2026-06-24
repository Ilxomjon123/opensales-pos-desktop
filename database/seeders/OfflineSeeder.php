<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Offline POS desktop — bitta lokal biznes: diller, egasi (kassir login)
 * va POS uchun "yo'l-yo'lakay xaridor" do'koni. Mahsulot katalogi alohida
 * [ProductCatalogSeeder] da. Internetsiz, markaziy serversiz ishlaydi.
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
    }
}
