<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * "Vkusniy mir" dilleri uchun kuryerlar (deliveryman) va ularni mavjud
 * buyurtmalarga biriktirish.
 */
final class VkusniyMirCourierSeeder extends Seeder
{
    public function run(): void
    {
        $dealer = Dealer::query()->where('name', 'like', '%kusn%')->first();

        if ($dealer === null) {
            $this->command->error('Vkusniy mir dilleri topilmadi.');

            return;
        }

        DB::transaction(function () use ($dealer): void {
            $couriers = $this->makeCouriers($dealer);
            $this->assignOrders($dealer, $couriers);
        });
    }

    /**
     * @return list<User>
     */
    private function makeCouriers(Dealer $dealer): array
    {
        $names = ['Курьер Дмитрий Соколов', 'Курьер Олег Кузнецов', 'Курьер Рустам Ахметов'];
        $suffix = (string) now()->format('His');
        $couriers = [];

        foreach ($names as $i => $name) {
            $couriers[] = User::query()->create([
                'name' => $name,
                'username' => 'vm_courier_'.$suffix.'_'.($i + 1),
                'phone' => '+79'.$suffix.'1'.$i,
                'password' => Hash::make('password'),
                'role' => UserRole::DELIVERYMAN,
                'dealer_id' => $dealer->id,
            ]);
        }

        return $couriers;
    }

    /**
     * Kuryer kerak bo'lgan statuslarga deliveryman_id + assigned_at qo'yish.
     *
     * @param  list<User>  $couriers
     */
    private function assignOrders(Dealer $dealer, array $couriers): void
    {
        $statuses = [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED];

        $orders = Order::query()
            ->where('dealer_id', $dealer->id)
            ->whereIn('status', $statuses)
            ->whereNull('deliveryman_id')
            ->get();

        $assigned = 0;

        foreach ($orders as $order) {
            $courier = $couriers[array_rand($couriers)];
            $order->deliveryman_id = $courier->id;

            // assigned_at: yig'ish boshlangandan keyin yoki buyurtma vaqtidan biroz keyin
            $base = $order->assembling_at ?? $order->created_at;
            $order->assigned_at = Carbon::parse($base)->addMinutes(fake()->numberBetween(1, 30));
            $order->save();
            $assigned++;
        }

        $this->command->info("  ✓ {$dealer->name} — ".count($couriers).' ta kuryer, '.$assigned.' ta buyurtmaga biriktirildi');
    }
}
