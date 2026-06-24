<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\PlatformPayment;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PlatformFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin statistikasini sinash uchun 1 yillik tarixiy ma'lumot:
 *  - Dillerlarga har xil komissiya stavkalari
 *  - Har dillerda 5-12 ta do'kon
 *  - 1 yil ichidagi buyurtmalar (taqsimlangan sanalar, statuslar)
 *  - Har buyurtmada 2-6 mahsulot
 *  - Platforma to'lovlari (oylik)
 *  - Komissiya stavkasi yil davomida o'zgargan — buyurtma snapshot qilingan stavkani saqlaydi
 */
final class HistoricalDataSeeder extends Seeder
{
    /** Odatdagi komissiya stavkalari — 0.1% dan 0.5% gacha. */
    private const DEFAULT_FEE_RATES = [
        'Lazzat Shirinliklar' => 0.25,
        'Toza Hayot (Gigiyena)' => 0.30,
        'Zulol Ichimliklar' => 0.20,
        'Coca-Cola Toshkent' => 0.35,
        'Anvar Non' => 0.15,
        'Shams Sut' => 0.40,
    ];

    private const SHOPS_PER_DEALER = [8, 15];

    /**
     * Umumiy do'kon registri — bir nechta diller bitta INN (bitta haqiqiy do'kon)
     * uchun savdo qilishi mumkin. Har dillerda alohida shops.id bo'ladi, lekin
     * INN bir xil → super admin panelida bitta do'kon sifatida guruhlanadi.
     *
     * Har bir yozuv: [inn, name, phone, region, district, address, contact_person]
     */
    private const SHARED_SHOP_REGISTRY = [
        ['301234501', 'Magnum Chilonzor', '+998901110501', 'Toshkent shahri', 'Chilonzor tumani', "Bunyodkor shoh ko'chasi, 12", 'Rustam Qodirov'],
        ['301234502', 'Havas Yunusobod', '+998901110502', 'Toshkent shahri', 'Yunusobod tumani', 'Amir Temur 45', 'Jasur Ismoilov'],
        ['301234503', 'Korzinka Mirzo Ulug\'bek', '+998901110503', 'Toshkent shahri', 'Mirzo Ulug\'bek tumani', "Mustaqillik ko'chasi 8", 'Nodira Azimova'],
        ['301234504', 'Makro Sergeli', '+998901110504', 'Toshkent shahri', 'Sergeli tumani', 'Yangi Sergeli 22', 'Bobur Saidov'],
        ['301234505', 'Lebara Mirobod', '+998901110505', 'Toshkent shahri', 'Mirobod tumani', "Shota Rustaveli ko'chasi 9", 'Dilshoda Tursunova'],
        ['301234506', 'Oro Yakkasaroy', '+998901110506', 'Toshkent shahri', 'Yakkasaroy tumani', 'Shahrisabz 14', 'Alisher Rahimov'],
        ['301234507', 'Anhor Market', '+998901110507', 'Toshkent shahri', 'Shayxontohur tumani', "Navoi ko'chasi 3", 'Zilola Ergasheva'],
        ['301234508', "Do'kon.uz Uchtepa", '+998901110508', 'Toshkent shahri', 'Uchtepa tumani', 'Katta Xalqa 17', "Zafar G'afforov"],
        ['301234509', 'Baraka Oqqo\'rg\'on', '+998901110509', 'Toshkent viloyati', 'Oqqo\'rg\'on tumani', "Markaziy ko'cha 4", 'Shavkat Yusupov'],
        ['301234510', 'Market+ Angren', '+998901110510', 'Toshkent viloyati', 'Angren shahri', 'Yoshlik 7', 'Sardor Nazarov'],
        ['301234511', 'Asl Market Chirchiq', '+998901110511', 'Toshkent viloyati', 'Chirchiq shahri', "A. Navoi ko'chasi 28", 'Farrux Karimov'],
        ['301234512', 'Nur Bekat Ohangaron', '+998901110512', 'Toshkent viloyati', 'Ohangaron shahri', 'Bahor 5', 'Umida Sobirova'],
        ['301234513', 'Samarqand Foodmart', '+998901110513', 'Samarqand viloyati', 'Samarqand shahri', "Registon ko'chasi 11", 'Botir Nuriddinov'],
        ['301234514', 'Bibi Xonim Store', '+998901110514', 'Samarqand viloyati', 'Samarqand shahri', 'Amir Temur 2', 'Sevara Abdullaeva'],
        ['301234515', 'Urgut Bozori', '+998901110515', 'Samarqand viloyati', 'Urgut tumani', "Markaziy bozor ko'chasi", "Akmal Xo'jaev"],
        ['301234516', 'Kattaqo\'rg\'on Optom', '+998901110516', 'Samarqand viloyati', 'Kattaqo\'rg\'on shahri', "Do'stlik 3", 'Bekzod Salomov'],
        ['301234517', 'Andijon Yangi Bozor', '+998901110517', 'Andijon viloyati', 'Andijon shahri', "Navoi shoh ko'chasi 5", 'Gulnoza Raxmatova'],
        ['301234518', 'Asaka Market', '+998901110518', 'Andijon viloyati', 'Asaka shahri', 'Yoshlik 19', 'Sherzod Yunusov'],
        ['301234519', 'Xo\'jaobod Do\'kon', '+998901110519', 'Andijon viloyati', 'Xo\'jaobod tumani', 'Markaziy 6', 'Dilbar Xoshimova'],
        ['301234520', 'Namangan Tong', '+998901110520', 'Namangan viloyati', 'Namangan shahri', "Mustaqillik ko'chasi 14", 'Otabek Toxtasinov'],
        ['301234521', 'Chust Plaza', '+998901110521', 'Namangan viloyati', 'Chust tumani', 'Bahor 8', 'Maqsad Qahhorov'],
        ['301234522', "Farg'ona Mega", '+998901110522', "Farg'ona viloyati", "Farg'ona shahri", "Al-Farg'oniy 10", "Dilshod G'afurov"],
        ['301234523', "Qo'qon Savdo", '+998901110523', "Farg'ona viloyati", "Qo'qon shahri", 'Imom Buxoriy 3', 'Saidakbar Mansurov'],
        ['301234524', 'Marg\'ilon Ipak Market', '+998901110524', "Farg'ona viloyati", 'Marg\'ilon shahri', 'Ipakchilar 2', 'Zebo Toshmurodova'],
        ['301234525', 'Buxoro Labi-Hovuz', '+998901110525', 'Buxoro viloyati', 'Buxoro shahri', "Bahouddin Naqshband ko'chasi 4", 'Akbar Rahmonov'],
        ['301234526', 'G\'ijduvon Tandir', '+998901110526', 'Buxoro viloyati', 'G\'ijduvon tumani', 'Markaz 1', 'Salima Ochilova'],
        ['301234527', "Xiva Ichan Qal'a Shop", '+998901110527', 'Xorazm viloyati', 'Xiva shahri', "Pahlavon Mahmud ko'chasi 7", 'Oybek Yusupov'],
        ['301234528', 'Urganch Bazar', '+998901110528', 'Xorazm viloyati', 'Urganch shahri', "Al-Xorazmiy ko'chasi 9", 'Malika Oripova'],
        ['301234529', "Qarshi Do'stlik", '+998901110529', 'Qashqadaryo viloyati', 'Qarshi shahri', "Mustaqillik ko'chasi 22", 'Ulug\'bek Turayev'],
        ['301234530', 'Termiz Yangi', '+998901110530', 'Surxondaryo viloyati', 'Termiz shahri', 'Bahor 11', 'Rayhon Quvondiqova'],
    ];

    /** Oyiga buyurtmalar soni — natijada oyiga ~1 mlrd oborot, eng kamida 300 mln. */
    private const ORDERS_PER_MONTH_RANGE = [100, 180];

    private const ITEMS_PER_ORDER_RANGE = [3, 8];

    public function run(): void
    {
        // Mahsulotlari bor barcha dillerlar — CatalogSeeder + DemoSeeder yaratganlar
        $dealers = Dealer::query()
            ->has('products')
            ->get();

        if ($dealers->isEmpty()) {
            $this->command?->warn('Mahsulotli diller topilmadi. Avval CatalogSeeder yoki DemoSeeder ni ishlating.');

            return;
        }

        foreach ($dealers as $dealer) {
            $this->seedForDealer($dealer);
        }

        $this->command?->info('✓ Tarixiy ma\'lumot tayyor — 1 yillik oborot, buyurtmalar va to\'lovlar');
    }

    private function seedForDealer(Dealer $dealer): void
    {
        $finalRate = self::DEFAULT_FEE_RATES[$dealer->name] ?? round(random_int(10, 50) / 100, 2);
        $dealer->update(['platform_fee_rate' => $finalRate]);

        $products = Product::query()->forDealer($dealer->id)->active()->get();

        if ($products->isEmpty()) {
            $this->command?->warn("  {$dealer->name}: mahsulot yo'q, o'tkazib yuborildi");

            return;
        }

        $this->wipeExistingHistory($dealer);

        $shops = $this->ensureShops($dealer);

        $orderCount = $this->seedOrders($dealer, $shops, $products, $finalRate);

        // Haqiqiy komissiyani hisoblab, to'lovlarni o'sha miqdor atrofida taqsimlaymiz
        $feeOwed = app(PlatformFinanceService::class)->feeOwedForDealer($dealer->fresh());
        $paymentCount = $this->seedPlatformPayments($dealer, $feeOwed);

        $this->command?->info("  ✓ {$dealer->name} — rate {$finalRate}%, {$shops->count()} do'kon, {$orderCount} buyurtma, {$paymentCount} to'lov");
    }

    /**
     * Qayta ishga tushirilganda dublikat bo'lmasligi uchun eski tarixiy yozuvlarni tozalash.
     * Registry dan tashqarida qolgan eski (faker bilan yaratilgan) do'konlar ham
     * o'chiriladi — shop_members, invites, orders, payments cascade bilan ketadi.
     */
    private function wipeExistingHistory(Dealer $dealer): void
    {
        $orderIds = DB::table('orders')->where('dealer_id', $dealer->id)->pluck('id');
        if ($orderIds->isNotEmpty()) {
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
        }

        DB::table('platform_payments')->where('dealer_id', $dealer->id)->delete();
        DB::table('payments')->where('dealer_id', $dealer->id)->delete();

        $registryInns = array_column(self::SHARED_SHOP_REGISTRY, 0);
        DB::table('shops')
            ->where('dealer_id', $dealer->id)
            ->whereNotIn('inn', $registryInns)
            ->delete();

        DB::table('shops')->where('dealer_id', $dealer->id)->update(['balance' => 0]);
    }

    /**
     * Har diller uchun umumiy registrydan 8-15 ta do'kon tanlaymiz.
     * Tanlash deterministik (dealer_id seed orqali) — qayta seed qilganda aynan
     * o'sha INN lar tanlanadi, updateOrCreate tufayli dublikat bo'lmaydi.
     * Natijada ayrim INN lar bir nechta dillerda turadi — bu shared-shop logikasini
     * super admin panelida sinash uchun kerak.
     *
     * @return Collection<int, Shop>
     */
    private function ensureShops(Dealer $dealer): Collection
    {
        $registry = self::SHARED_SHOP_REGISTRY;
        $targetCount = min(
            count($registry),
            $this->stableRandom($dealer->id, 'count', self::SHOPS_PER_DEALER[0], self::SHOPS_PER_DEALER[1]),
        );

        // Deterministik tartiblangan indeks ro'yxati — diller uchun har doim bir xil,
        // ammo dillerdan dillerga farqli → INN lar tabiiy ravishda "shared" bo'ladi.
        $indexes = range(0, count($registry) - 1);
        usort($indexes, fn (int $a, int $b) => $this->stableRandom($dealer->id, "sort_{$a}", 0, 9999)
            <=> $this->stableRandom($dealer->id, "sort_{$b}", 0, 9999));
        $indexes = array_slice($indexes, 0, $targetCount);

        $shops = collect();

        foreach ($indexes as $i) {
            [$inn, $name, $phone, $region, $district, $address, $contact] = $registry[$i];

            $shops->push(Shop::query()->updateOrCreate(
                ['dealer_id' => $dealer->id, 'inn' => $inn],
                [
                    'name' => $name,
                    'legal_name' => $name,
                    'phone' => $phone,
                    'region' => $region,
                    'district' => $district,
                    'address' => $address,
                    'contact_person' => $contact,
                    'balance' => 0,
                    'is_active' => true,
                ],
            ));
        }

        return $shops;
    }

    /**
     * Deterministik pseudo-random — (dealer, salt) juftligi uchun doim bir xil
     * qiymat qaytaradi. Shunda qayta seed qilganda dillerga aynan o'sha INN lar
     * tushadi.
     */
    private function stableRandom(int $dealerId, string $salt, int $min, int $max): int
    {
        $hash = crc32("d{$dealerId}:{$salt}");

        return $min + ($hash % ($max - $min + 1));
    }

    /**
     * Bir yil ichida buyurtmalar yaratish — har oyda taqsimlangan.
     * Stavka yil davomida bosqichma-bosqich oshadi (tarixiy o'zgarishlarni simulyatsiya qilish).
     */
    private function seedOrders(Dealer $dealer, Collection $shops, Collection $products, float $finalRate): int
    {
        // Yil boshida pastroq stavka, oxirida hozirgi stavka — tarixiy o'zgarishni simulyatsiya qilish
        $startRate = max(0.05, round($finalRate * 0.4, 2));
        $midRate = max(0.05, round($finalRate * 0.75, 2));

        $now = CarbonImmutable::now();
        $count = 0;
        $itemRows = [];
        $paymentRows = [];
        /** @var array<int, int> shop_id => balance delta */
        $shopBalances = [];

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $monthStart = $now->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();

            // Stavka o'zgarishi: dastlabki 4 oy — startRate, keyingi 4 — midRate, so'nggi 4 — finalRate
            $rateForMonth = match (true) {
                $monthsAgo >= 8 => $startRate,
                $monthsAgo >= 4 => $midRate,
                default => $finalRate,
            };

            $ordersThisMonth = random_int(self::ORDERS_PER_MONTH_RANGE[0], self::ORDERS_PER_MONTH_RANGE[1]);

            for ($i = 0; $i < $ordersThisMonth; $i++) {
                $createdAt = $this->randomDateBetween($monthStart, $monthEnd);
                $shop = $shops->random();
                // Real ulgurji buyurtma — ko'p kichik, ba'zi katta (log-normal taqsimot)
                $orderValueTarget = $this->randomOrderValue();
                $items = $this->pickItems($products, $orderValueTarget);
                $total = (int) round($items->sum(fn (array $it): float => $it['price'] * $it['qty']));
                $status = $this->randomStatus($monthsAgo);

                $deliveredAt = null;
                $deliveredTotal = null;
                $paidAmount = 0;

                if ($status === OrderStatus::DELIVERED) {
                    $deliveredAt = $createdAt->addDays(random_int(0, 3));
                    $deliveredTotal = random_int(0, 100) < 85
                        ? $total
                        : (int) round($total * (random_int(85, 99) / 100));
                    $paidAmount = random_int(0, 100) < 75
                        ? $deliveredTotal
                        : (int) round($deliveredTotal * (random_int(30, 100) / 100));
                }

                $orderId = DB::table('orders')->insertGetId([
                    'shop_id' => $shop->id,
                    'dealer_id' => $dealer->id,
                    'status' => $status->value,
                    'total' => $total,
                    'paid_amount' => $paidAmount,
                    'delivered_total' => $deliveredTotal,
                    'delivered_at' => $deliveredAt,
                    'platform_fee_rate' => $rateForMonth,
                    'note' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $deliveredAt ?? $createdAt,
                ]);

                foreach ($items as $it) {
                    $itemRows[] = [
                        'order_id' => $orderId,
                        'product_id' => $it['product_id'],
                        'product_name' => $it['product_name'],
                        'price' => $it['price'],
                        'qty' => $it['qty'],
                        'delivered_qty' => $status === OrderStatus::DELIVERED ? $it['qty'] : 0,
                        'unit' => $it['unit'],
                        'pack_size' => $it['pack_size'],
                        'pack_qty' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $deliveredAt ?? $createdAt,
                    ];
                }

                // Moliyaviy oqim — FinanceService::debit/credit ga mos payment rowlar
                $shopBalances[$shop->id] ??= 0;

                // Buyurtma yaratilgan → qarz (DEBIT)
                $paymentRows[] = $this->paymentRow(
                    $shop->id, $dealer->id, $orderId, $total, 'debit',
                    "Buyurtma #{$orderId}", $createdAt,
                );
                $shopBalances[$shop->id] -= $total;

                if ($status === OrderStatus::CANCELLED) {
                    // Bekor qilingan → qarzni qaytaramiz (CREDIT)
                    $paymentRows[] = $this->paymentRow(
                        $shop->id, $dealer->id, $orderId, $total, 'credit',
                        "Bekor qilindi: Buyurtma #{$orderId}", $createdAt->addHours(random_int(1, 24)),
                    );
                    $shopBalances[$shop->id] += $total;
                } elseif ($status === OrderStatus::DELIVERED) {
                    $adjustment = $total - ($deliveredTotal ?? $total);
                    if ($adjustment > 0) {
                        // Kam yetkazildi — farqni qaytaramiz (CREDIT)
                        $paymentRows[] = $this->paymentRow(
                            $shop->id, $dealer->id, $orderId, $adjustment, 'credit',
                            "Buyurtma #{$orderId} moslashtirildi (kam yetkazildi)", $deliveredAt,
                        );
                        $shopBalances[$shop->id] += $adjustment;
                    }
                    if ($paidAmount > 0) {
                        // Do'konchi to'lov qildi (CREDIT)
                        $paymentRows[] = $this->paymentRow(
                            $shop->id, $dealer->id, $orderId, $paidAmount, 'credit',
                            "Buyurtma #{$orderId} to'lov", $deliveredAt,
                        );
                        $shopBalances[$shop->id] += $paidAmount;
                    }
                }

                $count++;
            }
        }

        foreach (array_chunk($itemRows, 500) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        foreach (array_chunk($paymentRows, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        // Shop balanslarini real hisoblangan qiymatga o'rnatamiz
        foreach ($shopBalances as $shopId => $delta) {
            DB::table('shops')->where('id', $shopId)->update(['balance' => $delta]);
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private function paymentRow(int $shopId, int $dealerId, int $orderId, int $amount, string $type, string $note, CarbonImmutable $at): array
    {
        return [
            'shop_id' => $shopId,
            'dealer_id' => $dealerId,
            'order_id' => $orderId,
            'amount' => $amount,
            'type' => $type,
            'note' => $note,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /**
     * Real ulgurji buyurtma hajmi — skewed distribution.
     * Do'konlar odatda 1-10 mln oraliqda buyurtma qiladi, ba'zan yirik 30-80 mln.
     */
    private function randomOrderValue(): int
    {
        $bucket = random_int(1, 100);

        return match (true) {
            $bucket <= 35 => random_int(800_000, 3_500_000),       // kichik do'kon (35%)
            $bucket <= 75 => random_int(3_500_000, 12_000_000),    // o'rta (40%)
            $bucket <= 95 => random_int(12_000_000, 35_000_000),   // katta (20%)
            default => random_int(35_000_000, 80_000_000),         // yirik ulgurji (5%)
        };
    }

    /**
     * Mahsulotlar tanlab, ularga berilgan target summaga yaqin qty qo'yiladi.
     * Har line random ulush + variance → real buyurtma ko'rinishi.
     *
     * @return Collection<int, array{product_id:int,product_name:string,price:int,qty:int,unit:string,pack_size:int}>
     */
    private function pickItems(Collection $products, int $targetValue): Collection
    {
        $count = random_int(self::ITEMS_PER_ORDER_RANGE[0], self::ITEMS_PER_ORDER_RANGE[1]);
        $selected = $products->random(min($count, $products->count()))->values();
        $perLine = max(10_000, (int) round($targetValue / $selected->count()));

        return $selected->map(function (Product $p) use ($perLine): array {
            // Har line target summasini 60-140% atrofida olsin (variance)
            $lineTarget = (int) round($perLine * random_int(60, 140) / 100);
            $qty = max(1, (int) round($lineTarget / max(1, $p->price)));

            // Pack_size ko'paytiruvi — blok-blok buyurtma uchun
            if ($p->pack_size > 1) {
                $qty = (int) round($qty / $p->pack_size) * $p->pack_size;
                $qty = max($p->pack_size, $qty);
            }

            return [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'price' => $p->price,
                'qty' => $qty,
                'unit' => $p->unit->value,
                'pack_size' => (int) $p->pack_size,
            ];
        })->values();
    }

    /**
     * Statusi taqsimoti — eski buyurtmalar asosan yetkazilgan, yangilari ko'proq pending.
     */
    private function randomStatus(int $monthsAgo): OrderStatus
    {
        $roll = random_int(1, 100);

        if ($monthsAgo === 0) {
            return match (true) {
                $roll <= 40 => OrderStatus::PENDING,
                $roll <= 55 => OrderStatus::ASSEMBLING,
                $roll <= 90 => OrderStatus::DELIVERED,
                default => OrderStatus::CANCELLED,
            };
        }

        return match (true) {
            $roll <= 85 => OrderStatus::DELIVERED,
            $roll <= 92 => OrderStatus::CANCELLED,
            $roll <= 97 => OrderStatus::ASSEMBLING,
            default => OrderStatus::PENDING,
        };
    }

    private function randomDateBetween(CarbonImmutable $start, CarbonImmutable $end): CarbonImmutable
    {
        $span = $end->getTimestamp() - $start->getTimestamp();

        return CarbonImmutable::createFromTimestamp($start->getTimestamp() + random_int(0, $span));
    }

    /**
     * To'lovlar umumiy komissiyaning ~80-110% ga teng miqdorda bir yilga taqsimlanadi.
     * Shunda saldolar real (kichik + yoki −) ko'rinishida bo'ladi.
     */
    private function seedPlatformPayments(Dealer $dealer, int $feeOwed): int
    {
        if ($feeOwed <= 0) {
            return 0;
        }

        // Jami to'lov — komissiyaning 80..110% i (ba'zi qarzdor, ba'zi ortiqcha)
        $targetTotal = (int) round($feeOwed * random_int(80, 110) / 100);
        $paidSoFar = 0;
        $now = CarbonImmutable::now();
        $rows = [];

        // Tasodifiy oylar — 4..8 ta to'lov
        $paymentMonths = collect(range(0, 11))->shuffle()->take(random_int(4, 8))->sort()->values();

        $remainingPayments = $paymentMonths->count();

        foreach ($paymentMonths as $monthsAgo) {
            $remainingPayments--;
            $date = $now->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(random_int(5, 28));

            // So'nggi to'lov qolgan qismni to'liq qoplasin
            if ($remainingPayments === 0) {
                $amount = max(10_000, $targetTotal - $paidSoFar);
            } else {
                $remainingTotal = $targetTotal - $paidSoFar;
                $share = max(1, $remainingPayments + 1);
                // O'rtacha qismning 60..140% atrofida tasodifiy
                $base = max(10_000, (int) round($remainingTotal / $share));
                $amount = (int) round($base * random_int(60, 140) / 100);
                $amount = max(10_000, min($amount, $remainingTotal - $remainingPayments * 10_000));
            }

            $paidSoFar += $amount;

            $rows[] = [
                'dealer_id' => $dealer->id,
                'amount' => $amount,
                'note' => $date->format('m.Y').' oyi komissiyasi',
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        if ($rows !== []) {
            PlatformPayment::query()->insert($rows);
        }

        return count($rows);
    }
}
