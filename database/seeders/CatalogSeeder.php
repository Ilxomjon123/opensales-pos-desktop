<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductUnit;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 3 ta katta diller: shirinliklar, gigiyena, ichimliklar.
 * Har birida 100+ mahsulot va 1 ta SVG placeholder rasm.
 */
final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('public')->makeDirectory('products');

        foreach ($this->catalogs() as $cfg) {
            $this->seedDealer($cfg);
        }
    }

    /** @param array{name:string,username:string,bot_username:string,accent:string,products:array<string,array<int,array>>} $cfg */
    private function seedDealer(array $cfg): void
    {
        $dealer = Dealer::query()->updateOrCreate(
            ['bot_username' => $cfg['bot_username']],
            [
                'name' => $cfg['name'],
                'bot_token' => fake()->numerify('##########:').fake()->regexify('[A-Za-z0-9]{35}'),
                'is_active' => true,
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

        $categories = [];
        $order = 0;
        foreach (array_keys($cfg['products']) as $catName) {
            $categories[$catName] = ProductCategory::query()->updateOrCreate(
                ['dealer_id' => $dealer->id, 'name' => $catName],
                ['sort_order' => $order++, 'is_active' => true],
            );
        }

        $totalItems = array_sum(array_map('count', $cfg['products']));
        $this->command?->info("→ {$cfg['name']} — {$totalItems} ta mahsulot yuklanmoqda...");
        $bar = $this->command?->getOutput()->createProgressBar($totalItems);
        $bar?->start();

        $count = 0;
        $downloaded = 0;
        $fallback = 0;

        foreach ($cfg['products'] as $catName => $items) {
            $keywords = $this->keywordFor($catName);

            foreach ($items as $it) {
                $count++;

                $product = Product::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'name' => $it[0]],
                    [
                        'category_id' => $categories[$catName]->id,
                        'description' => $it[3] ?? null,
                        'price' => $it[1],
                        'stock' => random_int(80, 800),
                        'pack_size' => $it[2],
                        'unit' => ProductUnit::DONA,
                        'is_active' => true,
                    ],
                );

                // Eski SVG placeholderlarni o'chiramiz — haqiqiy fotoga almashtirish uchun
                foreach ($product->images()->where('path', 'like', '%.svg')->get() as $old) {
                    Storage::disk('public')->delete($old->path);
                    $old->delete();
                }

                if ($product->images()->count() === 0) {
                    $path = $this->downloadImage($keywords, $product->id);

                    if ($path !== null) {
                        $downloaded++;
                    } else {
                        $path = $this->generatePlaceholder($it[0], $cfg['accent'], $catName);
                        $fallback++;
                    }

                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'sort_order' => 0,
                    ]);
                }

                $bar?->advance();
            }
        }

        $bar?->finish();
        $this->command?->newLine();
        $this->command?->info(
            "  ✓ {$cfg['name']} — {$cfg['username']} / password"
            ." — {$count} ta mahsulot ({$downloaded} foto, {$fallback} fallback)"
        );
    }

    /**
     * Loremflickr dan kategoriyaga mos haqiqiy rasm yuklab olamiz.
     * `?lock={seed}` orqali har mahsulot uchun barqaror (lekin har xil) rasm.
     */
    private function downloadImage(string $keywords, int $seed): ?string
    {
        $encoded = rawurlencode($keywords);
        $url = "https://loremflickr.com/600/600/{$encoded}?lock={$seed}";

        try {
            $response = Http::timeout(20)
                ->retry(2, 400, throw: false)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) < 3_000) {
            return null;
        }

        // Magic bytes bo'yicha rasm ekanligini tasdiqlash
        $isJpeg = str_starts_with($body, "\xFF\xD8\xFF");
        $isPng = str_starts_with($body, "\x89PNG\r\n\x1A\n");
        if (! $isJpeg && ! $isPng) {
            return null;
        }

        $ext = $isPng ? 'png' : 'jpg';
        $filename = 'products/'.Str::random(40).'.'.$ext;
        Storage::disk('public')->put($filename, $body);

        return $filename;
    }

    /**
     * Kategoriya nomidan flickr uchun kalit so'zlar (vergul bilan ajratilgan).
     */
    private function keywordFor(string $categoryName): string
    {
        $map = [
            'Shokolad batonchiklar' => 'chocolate,bar,candy',
            'Shokolad plitkalar' => 'chocolate,bar,dessert',
            'Konfet qutilari' => 'chocolate,candy,box,sweets',
            'Pechenye va vafli' => 'cookies,biscuits,snack',
            'Saqich va karamel' => 'candy,gum,lollipop',
            'Sharbatli shirinliklar' => 'halva,dessert,sweet',
            'Tish pastalari' => 'toothpaste,tube',
            "Tish cho'tkalari" => 'toothbrush',
            'Shampunlar' => 'shampoo,bottle,haircare',
            'Dush gellari' => 'shower,gel,bottle',
            'Sovunlar' => 'soap,bar,bath',
            'Dezodorantlar' => 'deodorant,spray,bottle',
            'Ayollar gigiyenasi' => 'hygiene,package,pharmacy',
            "Qog'oz mahsulotlari" => 'toilet,paper,tissue',
            'Yuvish vositalari' => 'detergent,laundry,cleaning',
            'Gazli ichimliklar' => 'cola,soda,bottle,drink',
            'Tabiiy suvlar' => 'water,bottle,mineral',
            'Mineral gazli suvlar' => 'mineral,water,bottle',
            'Sharbat va sok' => 'juice,bottle,fruit',
            'Energetiklar' => 'energy,drink,can',
            'Choy ichimliklar' => 'tea,drink,bottle,iced',
            'Boshqa ichimliklar' => 'beverage,drink,bottle',
        ];

        return $map[$categoryName] ?? 'product';
    }

    private function generatePlaceholder(string $name, string $bg, string $categoryName): string
    {
        $initials = mb_strtoupper(mb_substr(Str::ascii($name), 0, 2));
        $safeName = htmlspecialchars(Str::limit($name, 40, ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeCategory = htmlspecialchars($categoryName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeInitials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
    <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="{$bg}" stop-opacity="1"/>
            <stop offset="1" stop-color="{$bg}" stop-opacity="0.55"/>
        </linearGradient>
    </defs>
    <rect width="600" height="600" fill="url(#g)"/>
    <circle cx="300" cy="255" r="135" fill="white" fill-opacity="0.18"/>
    <circle cx="300" cy="255" r="100" fill="white" fill-opacity="0.12"/>
    <text x="300" y="305" text-anchor="middle" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="130" font-weight="800" fill="white" letter-spacing="-3">{$safeInitials}</text>
    <text x="300" y="445" text-anchor="middle" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="26" font-weight="600" fill="white" fill-opacity="0.95">{$safeName}</text>
    <text x="300" y="480" text-anchor="middle" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="15" font-weight="500" fill="white" fill-opacity="0.7" letter-spacing="3">{$safeCategory}</text>
</svg>
SVG;

        $filename = 'products/'.Str::random(40).'.svg';
        Storage::disk('public')->put($filename, $svg);

        return $filename;
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogs(): array
    {
        return [
            [
                'name' => 'Lazzat Shirinliklar',
                'username' => 'lazzat',
                'bot_username' => 'lazzat_shirinliklar_bot',
                'accent' => '#e11d48',
                'products' => $this->sweetProducts(),
            ],
            [
                'name' => 'Toza Hayot (Gigiyena)',
                'username' => 'toza',
                'bot_username' => 'toza_hayot_bot',
                'accent' => '#0ea5e9',
                'products' => $this->hygieneProducts(),
            ],
            [
                'name' => 'Zulol Ichimliklar',
                'username' => 'zulol',
                'bot_username' => 'zulol_ichimlik_bot',
                'accent' => '#2563eb',
                'products' => $this->drinkProducts(),
            ],
        ];
    }

    /** @return array<string, array<int, array{0:string,1:int,2:int,3?:string}>> */
    private function sweetProducts(): array
    {
        return [
            'Shokolad batonchiklar' => [
                ['Snickers 40g', 5_500, 24],
                ['Snickers 50g', 6_500, 24],
                ['Snickers Super 95g', 12_000, 24],
                ['Mars 40g', 5_500, 24],
                ['Mars 50g', 6_500, 24],
                ['Mars Super 80g', 11_000, 24],
                ['Twix 50g', 6_500, 30],
                ['Twix Xtra 80g', 11_000, 24],
                ['Bounty 55g', 6_000, 24, 'Kokosli'],
                ['Bounty Double 110g', 11_500, 12],
                ['KitKat 4 finger 41.5g', 7_000, 24],
                ['KitKat Chunky 40g', 7_000, 24],
                ['Milka 45g', 7_000, 24, 'Alpine sut'],
                ['Alpen Gold 45g', 6_000, 24],
                ['Nesquik 43g', 6_500, 24],
                ['Dove 47g', 7_500, 24],
                ['Picnic 52g', 7_000, 24],
                ['Lion 60g', 8_000, 24],
                ['Wispa 36g', 6_000, 24],
                ['Milky Way 26g', 4_500, 24],
                ['3 Musketeers 54g', 7_000, 24],
            ],
            'Shokolad plitkalar' => [
                ['Alpen Gold 85g Bazovy', 15_000, 20],
                ['Alpen Gold 85g Cherry', 15_500, 20, 'Olcha parchalari bilan'],
                ['Alpen Gold 85g Oreo', 16_000, 20],
                ['Alpen Gold 85g Almonds', 16_500, 20],
                ['Alpen Gold 180g', 28_000, 12],
                ['Milka 85g Alpine Milk', 18_000, 20],
                ['Milka 85g Hazelnuts', 19_000, 20],
                ['Milka 85g Oreo', 19_500, 20],
                ['Milka 270g', 52_000, 12],
                ['Nestle Classic 100g', 17_000, 20],
                ['Ritter Sport 100g Molochny', 22_000, 15],
                ['Ritter Sport 100g Lesnoy Orex', 24_000, 15],
                ['Ritter Sport 100g Tseliy Fundukli', 26_000, 15],
                ['Babaevsky 100g', 15_000, 20],
                ['Krasnyi Oktyabr 100g', 14_000, 20],
                ['Rossija Shchedraya Dusha 100g', 16_000, 20],
                ['Shokoladnitsa 90g', 13_500, 20],
                ['Svitoch 90g', 13_000, 20],
            ],
            'Konfet qutilari' => [
                ['Raffaello 90g', 28_000, 12],
                ['Raffaello 240g', 75_000, 6],
                ['Merci Classic 250g', 55_000, 12],
                ['Merci Crispy 250g', 58_000, 12],
                ['Ferrero Rocher 125g (10 pc)', 70_000, 10],
                ['Ferrero Rocher 300g (24 pc)', 155_000, 6],
                ['Ferrero Collection 172g', 95_000, 8],
                ['Kinder Chocolate 100g', 32_000, 15],
                ['Kinder Bueno 43g', 12_000, 30],
                ['Lyubitelskie 250g', 26_000, 12, 'Fabrika Roshen'],
                ['Klever 250g', 28_000, 12],
                ['Mishka Kosolapy 250g', 32_000, 12],
                ['Belochka 250g', 28_000, 12],
                ['Ptichye Moloko 250g', 38_000, 12],
                ['Krasnyi Mak 250g', 24_000, 12],
                ['Ptichka Roshen 180g', 30_000, 12],
                ['Roshen Karavan 180g', 28_000, 12],
                ['Gulliver 250g', 26_000, 12],
                ['Trufelnaya 250g', 35_000, 12],
                ['Chokopay 12pc Lotte', 22_000, 16],
            ],
            'Pechenye va vafli' => [
                ['Oreo Original 133g', 15_000, 20],
                ['Oreo Chocolate 133g', 15_500, 20],
                ['Oreo Strawberry 95g', 12_000, 20],
                ['Halvita Milk 150g', 8_500, 20, 'Sutli kremli'],
                ['Halvita Vanilla 150g', 8_500, 20],
                ['Halvita Chocolate 150g', 9_000, 20],
                ['Yubileynoe Milk 134g', 9_000, 24],
                ['Yubileynoe Morning 116g', 8_500, 24],
                ['Belvita Medvedok 300g', 28_000, 12],
                ['Belvita Classic 200g', 18_000, 15],
                ['Barni Chocolate 150g', 14_000, 20],
                ['Barni Milk 150g', 14_000, 20],
                ['Vafli Artek 1kg', 35_000, 6],
                ['Vafli Shokoladnye 1kg', 38_000, 6],
                ['Chokopie Orion 12pc', 24_000, 12],
                ['Mondelez Alpen Gold 150g', 18_000, 16],
                ['Ovsyanoe 300g', 15_000, 12],
                ['Tvorozhnoe 200g', 14_000, 12],
                ['Tasty Biscuit 180g', 11_000, 16],
                ['Choco Leibniz 125g', 22_000, 15],
            ],
            'Saqich va karamel' => [
                ['Orbit Strawberry 14g', 3_500, 30],
                ['Orbit White 14g', 3_500, 30],
                ['Orbit Menthol 14g', 3_500, 30],
                ['Orbit Professional 14g', 4_000, 30],
                ['Dirol Mint 13.5g', 3_000, 30],
                ['Dirol Fruit 13.5g', 3_000, 30],
                ['Mentos Rainbow 37g', 7_000, 20],
                ['Mentos Mint 37g', 7_000, 20],
                ['Eclipse Pepper 13.6g', 4_500, 30],
                ['Hubba Bubba 28g', 6_000, 20],
                ["Wrigley's Doublemint 13.5g", 3_500, 30],
                ['Stimorol Tropical 13.5g', 3_500, 30],
                ['Chupa Chups 10pc', 12_000, 24],
                ['Chupa Chups Crazy Dips', 8_000, 24],
                ['Karamel Fruktovaya 200g', 18_000, 15],
                ['Karamel Molochnaya 200g', 18_000, 15],
            ],
            'Sharbatli shirinliklar' => [
                ['Halva Gulshan 300g', 22_000, 15, 'Kungaboqar halvosi'],
                ['Halva Podsolnechnaya 300g', 20_000, 15],
                ['Halva Fundukli 250g', 28_000, 12],
                ['Pastila Belevskaya 250g', 32_000, 12],
                ['Zefir Klassicheskiy 300g', 22_000, 15],
                ['Zefir Chocolate 300g', 28_000, 12],
                ['Marshmallow Mini 100g', 14_000, 20],
                ['Zefir V Shokolade 200g', 26_000, 15],
                ['Rahat-lukum 500g', 28_000, 10],
                ['Kozinaki Kungaboqar 200g', 14_000, 15],
            ],
        ];
    }

    /** @return array<string, array<int, array{0:string,1:int,2:int,3?:string}>> */
    private function hygieneProducts(): array
    {
        return [
            'Tish pastalari' => [
                ['Colgate Total 100ml', 28_000, 12],
                ['Colgate Total 150ml', 38_000, 12],
                ['Colgate Max Fresh Citrus 100ml', 27_000, 12],
                ['Colgate Maximum White 100ml', 32_000, 12],
                ['Colgate Naturals 75ml', 22_000, 12],
                ['Colgate Kids Strawberry 60ml', 18_000, 12],
                ['Blend-a-med 3D White 100ml', 34_000, 12],
                ['Blend-a-med Classic 100ml', 26_000, 12],
                ['Blend-a-med Complete 100ml', 32_000, 12],
                ['Blend-a-med Pro-Expert 100ml', 42_000, 12],
                ['Sensodyne Rapid Relief 75ml', 58_000, 10],
                ['Sensodyne Classic 75ml', 48_000, 10],
                ['Sensodyne Repair 75ml', 62_000, 10],
                ['Parodontax Classic 75ml', 52_000, 10],
                ['Parodontax Whitening 75ml', 58_000, 10],
                ['Aquafresh Triple Protection 75ml', 22_000, 12],
                ['Lacalut Aktiv 75ml', 65_000, 10],
                ['Lacalut White 75ml', 72_000, 10],
                ['ROCS Milk 45ml (bolalar)', 45_000, 12],
                ['ROCS Uno 75ml', 48_000, 10],
            ],
            'Tish cho\'tkalari' => [
                ['Colgate Classic Medium', 8_000, 24],
                ['Colgate 360 Advanced', 22_000, 12],
                ['Colgate Extra Clean', 9_500, 24],
                ['Oral-B Pro Expert', 24_000, 12],
                ['Oral-B 3D White Luxe', 28_000, 12],
                ['Oral-B Junior', 18_000, 12],
                ['Sensodyne Sensitive', 22_000, 12],
                ['Splat Black Whitening', 32_000, 12],
                ['Jordan Ultralite', 16_000, 12],
                ['Reach Interdental', 14_000, 12],
            ],
            'Shampunlar' => [
                ['Head & Shoulders Classic 200ml', 48_000, 12],
                ['Head & Shoulders Classic 400ml', 78_000, 8],
                ['Head & Shoulders Menthol 400ml', 82_000, 8],
                ['Head & Shoulders Anti-dandruff 200ml', 52_000, 12],
                ['Pantene Pro-V Volume 200ml', 45_000, 12],
                ['Pantene Pro-V Volume 400ml', 78_000, 8],
                ['Pantene Repair 400ml', 82_000, 8],
                ['Pantene Hydra 250ml', 55_000, 12],
                ['Shamtu Volume 380ml', 32_000, 12],
                ['Shamtu Freshness 380ml', 32_000, 12],
                ['Sunsilk Coconut 200ml', 28_000, 12],
                ['Sunsilk Citrus 400ml', 48_000, 8],
                ['Clear Men Active 250ml', 52_000, 12],
                ['Clear Men Sport 400ml', 82_000, 8],
                ['Timotei Camomile 250ml', 38_000, 12],
                ['Schwarzkopf Gliss Kur 250ml', 62_000, 10],
                ['Schwarzkopf Natural Moisture 250ml', 58_000, 10],
                ['Loreal Elseve Full 400ml', 85_000, 8],
                ['Loreal Elseve Color 400ml', 85_000, 8],
                ['Loreal Elseve Repair 400ml', 85_000, 8],
                ['Syoss Professional 500ml', 95_000, 8],
                ['Nivea Shine 250ml', 42_000, 12],
                ['Garnier Fructis Oil Repair 250ml', 48_000, 12],
                ['Garnier Fructis Color Last 400ml', 78_000, 8],
            ],
            'Dush gellari' => [
                ['Nivea Men Energy 250ml', 42_000, 12],
                ['Nivea Care 250ml', 40_000, 12],
                ['Nivea Cherry 250ml', 42_000, 12],
                ['Palmolive Thermal 250ml', 36_000, 12],
                ['Fa Sport 250ml', 32_000, 12],
                ['Camay Classic 250ml', 38_000, 12],
                ['Dove Original 250ml', 48_000, 12],
                ['Dove Nutrium Moisture 250ml', 52_000, 12],
                ['Axe Black 250ml', 48_000, 12],
                ['Axe Dark Temptation 250ml', 52_000, 12],
                ['Adidas Active Start 400ml', 62_000, 8],
                ['Old Spice Captain 400ml', 72_000, 8],
            ],
            'Sovunlar' => [
                ['Dove Beauty Bar 100g', 18_000, 24],
                ['Dove Beauty Bar 3x100g', 48_000, 10],
                ['Duru 1+1 Aloe 140g', 12_000, 24],
                ['Duru Honey 140g', 12_000, 24],
                ['Palmolive Aloe 90g', 10_000, 30],
                ['Safeguard Classic Menthol 135g', 14_000, 24],
                ['Nivea Creme Care 90g', 12_000, 24],
                ['Cussons Imperial Leather 100g', 13_000, 24],
                ['Absolut Antibacterial 100g', 11_000, 24],
                ['Camay Romantique 85g', 10_500, 24],
                ['Moya Semya Lavanda 110g', 7_500, 30],
                ['Neva 90g', 5_000, 30],
            ],
            'Dezodorantlar' => [
                ['Rexona Men Active 150ml', 42_000, 12],
                ['Rexona Men Cobalt 150ml', 42_000, 12],
                ['Rexona Women Shower Fresh 150ml', 42_000, 12],
                ['Rexona Women Invisible 150ml', 44_000, 12],
                ['Nivea Men Fresh 150ml', 38_000, 12],
                ['Nivea Women Dry Roll-on 50ml', 32_000, 12],
                ['Old Spice Captain 150ml', 48_000, 12],
                ['Old Spice Denali 150ml', 48_000, 12],
                ['Axe Black 150ml', 42_000, 12],
                ['Axe Dark Temptation 150ml', 45_000, 12],
                ['Dove Original 150ml', 38_000, 12],
                ['Dove Go Fresh 150ml', 40_000, 12],
                ['Garnier Men 150ml', 36_000, 12],
                ['Adidas Sport 150ml', 38_000, 12],
            ],
            'Ayollar gigiyenasi' => [
                ['Always Classic Normal 10pc', 22_000, 16],
                ['Always Classic Night 7pc', 24_000, 16],
                ['Always Ultra Normal 10pc', 28_000, 16],
                ['Always Ultra Super 8pc', 28_000, 16],
                ['Naturella Classic 10pc', 19_000, 16],
                ['Naturella Ultra 10pc', 22_000, 16],
                ['Kotex Natural 10pc', 24_000, 16],
                ['Libresse Invisible 10pc', 26_000, 16],
                ['Bella Classic 10pc', 18_000, 16],
                ['Naturella Daily 20pc', 16_000, 16],
            ],
            'Qog\'oz mahsulotlari' => [
                ['Kleo Premium 8-pack', 28_000, 12],
                ['Kleo Classic 4-pack', 14_000, 20],
                ['Zewa Premium 8-pack', 42_000, 8],
                ['Zewa Aroma 4-pack', 22_000, 16],
                ['Zewa Natural 4-pack', 22_000, 16],
                ['Mola Plus 8-pack', 26_000, 12],
                ['Plushevye 4-pack', 12_000, 20],
                ['Zewa Salfetki 100pc', 18_000, 16],
                ['Cotton Soft Salfetki 150pc', 22_000, 16],
                ['Kosmeticheskie Salfetki 200pc', 16_000, 20],
            ],
            'Yuvish vositalari' => [
                ['Ariel Color 3kg', 85_000, 6],
                ['Ariel Mountain Spring 3kg', 85_000, 6],
                ['Ariel Automat 6kg', 165_000, 4],
                ['Tide Color 3kg', 78_000, 6],
                ['Tide Universal 3kg', 78_000, 6],
                ['Persil Power 3kg', 95_000, 6],
                ['Persil Sensitive 3kg', 95_000, 6],
                ['BiMax Active 3kg', 52_000, 6],
                ['Losk Color 3kg', 62_000, 6],
                ['Myth Lemon 4kg', 48_000, 6],
                ['Lenor Rainbow 1L', 45_000, 10],
                ['Vernel Classic 1L', 38_000, 10],
                ['Fairy Lemon 450ml', 22_000, 20],
                ['Fairy Aloe 900ml', 38_000, 12],
                ['AOS Lemon 450ml', 14_000, 20],
                ['Sorti Lemon 900ml', 18_000, 16],
                ['Pemolux Soda 500g', 12_000, 20],
                ['ACE Bleach 1L', 22_000, 15],
                ['Belizna 1L', 7_000, 20],
            ],
        ];
    }

    /** @return array<string, array<int, array{0:string,1:int,2:int,3?:string}>> */
    private function drinkProducts(): array
    {
        return [
            'Gazli ichimliklar' => [
                ['Coca-Cola 0.33L banka', 6_500, 24],
                ['Coca-Cola 0.5L', 7_000, 24],
                ['Coca-Cola 1L', 11_000, 12],
                ['Coca-Cola 1.5L', 15_000, 6],
                ['Coca-Cola 2L', 19_000, 6],
                ['Coca-Cola Zero 0.5L', 7_500, 24, 'Shakarsiz'],
                ['Coca-Cola Zero 1L', 12_000, 12],
                ['Coca-Cola Vanilla 0.5L', 8_500, 24],
                ['Coca-Cola Cherry 0.5L', 8_500, 24],
                ['Pepsi 0.33L banka', 6_000, 24],
                ['Pepsi 0.5L', 6_500, 24],
                ['Pepsi 1L', 10_500, 12],
                ['Pepsi 1.5L', 14_500, 6],
                ['Pepsi 2L', 18_500, 6],
                ['Pepsi Lite 0.5L', 7_000, 24],
                ['Pepsi Lite 1L', 11_000, 12],
                ['Fanta Orange 0.5L', 6_500, 24],
                ['Fanta Orange 1L', 10_500, 12],
                ['Fanta Orange 1.5L', 14_500, 6],
                ['Fanta Grape 0.5L', 6_500, 24],
                ['Sprite 0.33L banka', 6_000, 24],
                ['Sprite 0.5L', 6_500, 24],
                ['Sprite 1L', 10_500, 12],
                ['Sprite 1.5L', 14_500, 6],
                ['7UP 0.5L', 6_500, 24],
                ['7UP 1L', 10_500, 12],
                ['Mirinda Orange 0.5L', 6_000, 24],
                ['Mirinda Orange 1L', 10_000, 12],
                ['Mirinda Strawberry 0.5L', 6_000, 24],
                ['Mountain Dew 0.5L', 7_500, 24],
                ['Mountain Dew 1L', 12_000, 12],
                ['RC Cola 0.5L', 5_500, 24],
                ['RC Cola 1L', 9_000, 12],
            ],
            'Tabiiy suvlar' => [
                ['Bonaqua 0.33L', 3_000, 24, 'Gazsiz ichimlik suvi'],
                ['Bonaqua 0.5L', 3_500, 24],
                ['Bonaqua 1L', 5_500, 12],
                ['Bonaqua 1.5L', 6_500, 12],
                ['Bonaqua 5L', 13_000, 4],
                ['Nestle Pure Life 0.5L', 4_000, 24],
                ['Nestle Pure Life 1.5L', 7_000, 12],
                ['Arashan 0.5L', 3_000, 24],
                ['Arashan 1.5L', 5_500, 12],
                ['Arashan 5L', 10_000, 4],
                ['Hayot 0.5L', 3_000, 24],
                ['Hayot 1.5L', 5_000, 12],
                ['Hayot 5L', 10_500, 4],
                ['Obi Zulol 0.5L', 3_000, 24],
                ['Obi Zulol 1.5L', 5_500, 12],
                ['Dilnoza 1.5L', 5_000, 12],
                ['Aquafina 1L', 6_000, 12],
            ],
            'Mineral gazli suvlar' => [
                ['Borjomi 0.5L', 18_000, 12, 'Gruziya mineral'],
                ['Borjomi 1L', 28_000, 8],
                ['Essentuki-17 0.5L', 12_000, 12],
                ['Essentuki-4 0.5L', 11_000, 12],
                ['Narzan 0.5L', 9_000, 12],
                ['Narzan 1.5L', 14_000, 8],
                ['Noravank 0.5L', 8_000, 12],
                ['Shahar Gazli 0.5L', 3_500, 24],
                ['Shahar Gazli 1L', 5_500, 12],
            ],
            'Sharbat va sok' => [
                ['Rich Apple 1L', 24_000, 12],
                ['Rich Orange 1L', 25_000, 12],
                ['Rich Tomato 1L', 22_000, 12],
                ['Rich Mango 1L', 28_000, 12],
                ['Rich Pomegranate 1L', 32_000, 12],
                ['Rich Peach 1L', 26_000, 12],
                ['Rich Pineapple 1L', 28_000, 12],
                ['Dobry Apple 1L', 18_000, 12],
                ['Dobry Multifruit 1L', 19_000, 12],
                ['Dobry Orange 1L', 19_000, 12],
                ['Chudo Detskoe 950ml', 16_000, 12, 'Bolalar uchun'],
                ['J7 Apple 0.97L', 22_000, 12],
                ['J7 Orange 0.97L', 22_000, 12],
                ['Sandora Apple 1L', 20_000, 12],
                ['Lyubimyi Multi 950ml', 17_000, 12],
                ['Santal Peach 1L', 24_000, 12],
                ['Caprisun Orange 200ml', 5_500, 30],
                ['Tropicana Orange 1L', 32_000, 12],
                ['Gippy Nectar 200ml', 3_500, 30],
                ['Fruit Master Apple 1L', 15_000, 12],
            ],
            'Energetiklar' => [
                ['Red Bull 250ml', 22_000, 24, 'Tonus beruvchi'],
                ['Red Bull 355ml', 30_000, 24],
                ['Red Bull Sugar-Free 250ml', 22_000, 24],
                ['Monster Green 500ml', 28_000, 12],
                ['Monster Mango 500ml', 30_000, 12],
                ['Hell Classic 250ml', 9_000, 24],
                ['Hell Sugar-free 250ml', 9_000, 24],
                ['Burn Original 250ml', 12_000, 24],
                ['Adrenalin Rush 500ml', 18_000, 12],
                ['Flash Energy 250ml', 7_500, 24],
            ],
            'Choy ichimliklar' => [
                ['Fuse Tea Peach 1L', 12_000, 12],
                ['Fuse Tea Lemon 1L', 12_000, 12],
                ['Fuse Tea Mango 0.5L', 7_000, 24],
                ['Fuse Tea Peach 1.5L', 16_000, 8],
                ['Lipton Ice Tea Lemon 0.5L', 7_500, 24],
                ['Lipton Ice Tea Lemon 1L', 12_500, 12],
                ['Lipton Ice Tea Peach 1L', 12_500, 12],
                ['Nestea Lemon 1L', 11_000, 12],
                ['Nestea Peach 1L', 11_000, 12],
                ['Pickwick Fruity 0.5L', 7_000, 24],
            ],
            'Boshqa ichimliklar' => [
                ['Aloe King 240ml', 14_000, 20],
                ['Coconut Water 310ml', 22_000, 12],
                ['Ayron 1L', 10_000, 12],
                ['Ayron 0.5L', 6_000, 20],
                ['Kvas Nikola 1L', 9_000, 12],
                ['Kvas Ochakovsky 1.5L', 13_000, 8],
                ['Smoothie Apple 200ml', 15_000, 20],
                ['Smoothie Mango 200ml', 16_000, 20],
            ],
        ];
    }
}
