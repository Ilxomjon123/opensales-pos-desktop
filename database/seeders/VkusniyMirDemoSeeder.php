<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "Vkusniy mir" dilleri uchun demo mijozlar (do'konlar) va buyurtmalar.
 * 20 ta mijoz, 120 ta buyurtma — barcha statuslar bo'yicha taqsimlangan.
 */
final class VkusniyMirDemoSeeder extends Seeder
{
    private const CUSTOMERS = 20;

    private const ORDERS = 120;

    public function run(): void
    {
        $dealer = Dealer::query()->where('name', 'like', '%kusn%')->first();

        if ($dealer === null) {
            $this->command->error('Vkusniy mir dilleri topilmadi.');

            return;
        }

        $products = Product::query()
            ->where('dealer_id', $dealer->id)
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->get(['id', 'name', 'price', 'unit'])
            ->all();

        if ($products === []) {
            $this->command->error('Vkusniy mir mahsulotlari topilmadi.');

            return;
        }

        DB::transaction(function () use ($dealer, $products): void {
            $this->seed($dealer, $products);
        });
    }

    /**
     * @param  list<Product>  $products
     */
    private function seed(Dealer $dealer, array $products): void
    {
        // --- Mijozlar (har biri = do'kon + shop_member + customer akkaunti) ---
        $shopNames = [
            'Магазин «Перекрёсток»', 'Универсам «Берёзка»', 'Продукты «Светофор»',
            'Минимаркет «Уют»', 'Гастроном «Центральный»', 'Магазин «Колосок»',
            'Лавка «Вкуснотеево»', 'Супермаркет «Радуга»', 'Продукты «У дома»',
            'Магазин «Пятёрочка-2»', 'Маркет «Солнышко»', 'Гастроном «Восток»',
            'Магазин «Family»', 'Продукты «Околица»', 'Минимаркет «24 часа»',
            'Магазин «Калинка»', 'Лавка «Свежесть»', 'Супермаркет «Магнит-3»',
            'Продукты «Дружба»', 'Магазин «Хороший»',
        ];

        $suffix = (string) now()->format('His');
        $members = [];

        foreach (range(1, self::CUSTOMERS) as $i) {
            $name = fake('ru_RU')->name();

            $customer = Customer::query()->create([
                'phone' => '+79'.$suffix.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'locale' => 'ru',
                'is_active' => true,
                'last_login_at' => now()->subDays(fake()->numberBetween(0, 30)),
            ]);

            $shop = Shop::query()->create([
                'dealer_id' => $dealer->id,
                'country_id' => $dealer->country_id,
                'name' => $shopNames[$i - 1] ?? ('Магазин №'.$i),
                'phone' => $customer->phone,
                'address' => fake('ru_RU')->address(),
                'contact_person' => $name,
                'inn' => $suffix.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'latitude' => fake()->randomFloat(7, 55.5, 55.9),   // Moskva atrofi
                'longitude' => fake()->randomFloat(7, 37.3, 37.9),
                'map_provider' => 'manual',
                'balance' => 0,
                'is_active' => true,
            ]);

            $members[] = ShopMember::query()->create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'telegram_id' => (int) ('7'.$suffix.str_pad((string) $i, 3, '0', STR_PAD_LEFT)),
                'name' => $name,
                'locale' => 'ru',
                'is_active' => true,
                'joined_at' => now()->subDays(fake()->numberBetween(10, 120)),
                'last_seen_at' => now()->subDays(fake()->numberBetween(0, 20)),
            ]);
        }

        // --- Buyurtmalar ---
        // Status taqsimoti (jami 120):
        $statusPlan = array_merge(
            array_fill(0, 18, OrderStatus::PENDING),
            array_fill(0, 14, OrderStatus::ASSEMBLING),
            array_fill(0, 14, OrderStatus::DELIVERING),
            array_fill(0, 34, OrderStatus::DELIVERED),
            array_fill(0, 26, OrderStatus::RECEIVED),
            array_fill(0, 14, OrderStatus::CANCELLED),
        );
        shuffle($statusPlan);

        $number = (int) (Order::query()->where('dealer_id', $dealer->id)->max('number') ?? 0);
        $monthCounters = [];
        $created = array_fill_keys(array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()), 0);

        $cancelReasons = ['Mijoz bekor qildi', 'Mahsulot tugagan', 'Yetkazib berish imkonsiz', 'Narx kelishilmadi'];

        foreach ($statusPlan as $status) {
            $member = $members[array_rand($members)];
            $placedAt = Carbon::now()->subDays(fake()->numberBetween(0, 89))
                ->setTime(fake()->numberBetween(8, 21), fake()->numberBetween(0, 59));

            $period = (int) $placedAt->format('Ym');
            $monthCounters[$period] = ($monthCounters[$period] ?? 0) + 1;

            $order = new Order([
                'shop_id' => $member->shop_id,
                'member_id' => $member->id,
                'dealer_id' => $dealer->id,
                'currency' => $dealer->currency,
                'channel' => fake()->randomElement([OrderChannel::BOT, OrderChannel::MOBILE_APP, OrderChannel::MANUAL]),
                'number' => ++$number,
                'month_number' => $monthCounters[$period],
                'status' => $status,
                'note' => fake()->optional(0.3)->sentence(),
            ]);
            $order->created_at = $placedAt;
            $order->updated_at = $placedAt;
            $order->save();

            // 1-6 ta item, real mahsulotlardan
            $picked = fake()->randomElements($products, fake()->numberBetween(1, min(6, count($products))));
            $total = 0;

            foreach ($picked as $product) {
                $qty = (float) fake()->numberBetween(1, 30);
                $price = (float) $product->price;
                $total += (int) round($qty * $price);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $price,
                    'qty' => $qty,
                    'unit' => $product->unit,
                    'pack_size' => 1,
                    'pack_qty' => null,
                ]);
            }

            $this->applyLifecycle($order, $status, $placedAt, $total, $cancelReasons);
            $created[$status->value]++;
        }

        $this->report($dealer, $created);
    }

    /**
     * Status bo'yicha vaqt belgilarini va summalarni to'ldirish.
     *
     * @param  list<string>  $cancelReasons
     */
    private function applyLifecycle(Order $order, OrderStatus $status, Carbon $placedAt, int $total, array $cancelReasons): void
    {
        $order->total = $total;
        $t = $placedAt->copy();

        if (in_array($status, [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            $order->assembling_at = $t = $t->copy()->addMinutes(fake()->numberBetween(5, 90));
        }

        if (in_array($status, [OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            $order->delivering_at = $t = $t->copy()->addMinutes(fake()->numberBetween(20, 180));
        }

        if (in_array($status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true)) {
            $order->delivered_at = $t = $t->copy()->addMinutes(fake()->numberBetween(30, 240));
            $order->delivered_total = $total;
            $order->paid_amount = fake()->boolean(70) ? $total : (int) round($total * fake()->randomFloat(2, 0.3, 0.9));
        }

        if ($status === OrderStatus::RECEIVED) {
            $order->received_at = $t->copy()->addMinutes(fake()->numberBetween(5, 120));
            $order->received_by_member_id = $order->member_id;
            $order->paid_amount = $total;
        }

        if ($status === OrderStatus::CANCELLED) {
            $order->cancelled_at = $placedAt->copy()->addMinutes(fake()->numberBetween(5, 300));
            $order->cancellation_reason = fake()->randomElement($cancelReasons);
        }

        $order->save();
    }

    /**
     * @param  array<string,int>  $created
     */
    private function report(Dealer $dealer, array $created): void
    {
        $this->command->info("  ✓ {$dealer->name} — ".self::CUSTOMERS.' ta mijoz, '.array_sum($created).' ta buyurtma');
        foreach ($created as $status => $count) {
            $this->command->info("      {$status}: {$count}");
        }
    }
}
