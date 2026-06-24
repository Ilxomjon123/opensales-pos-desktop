<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diller + oy (YYYY-MM) bo'yicha buyurtma raqami uchun atomik hisoblagich.
        // Har oy boshida 1 dan boshlanadi. OrderNumberService bitta UPSERT bilan
        // raqamni oshirib qaytaradi — raqib yozishlar row-level lock orqali serial.
        Schema::create('dealer_order_month_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['dealer_id', 'period']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('month_number')->nullable()->after('number');
        });

        $this->backfillExistingOrders();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('month_number');
        });

        Schema::dropIfExists('dealer_order_month_counters');
    }

    /**
     * Mavjud buyurtmalarni diller + oy (created_at bo'yicha) ichida id tartibida
     * 1 dan raqamlash va counters jadvalini har oy uchun oxirgi qiymat bilan to'ldirish.
     */
    private function backfillExistingOrders(): void
    {
        $now = now();

        $orders = DB::table('orders')
            ->select('id', 'dealer_id', 'created_at')
            ->orderBy('dealer_id')
            ->orderBy('id')
            ->get();

        /** @var array<string, int> $counters period-key => last_number */
        $counters = [];

        foreach ($orders as $order) {
            $period = substr((string) $order->created_at, 0, 7); // YYYY-MM
            $key = $order->dealer_id.'|'.$period;
            $next = ($counters[$key] ?? 0) + 1;
            $counters[$key] = $next;

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['month_number' => $next]);
        }

        foreach ($counters as $key => $lastNumber) {
            [$dealerId, $period] = explode('|', $key);

            DB::table('dealer_order_month_counters')->insert([
                'dealer_id' => (int) $dealerId,
                'period' => $period,
                'last_number' => $lastNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
