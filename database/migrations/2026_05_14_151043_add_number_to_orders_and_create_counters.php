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
        // Diller bo'yicha buyurtma raqami uchun atomik hisoblagich.
        // Bitta UPSERT statement ichida last_number oshiriladi va qaytariladi —
        // bir vaqtning o'zida kelgan ikki yozish row-level lock orqali
        // serial bo'lib qoladi (PG va SQLite ikkalasida qo'llab-quvvatlanadi).
        Schema::create('dealer_order_counters', function (Blueprint $table) {
            $table->foreignId('dealer_id')
                ->primary()
                ->constrained('dealers')
                ->cascadeOnDelete();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('dealer_id');
        });

        $this->backfillExistingOrders();

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['dealer_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['dealer_id', 'number']);
            $table->dropColumn('number');
        });

        Schema::dropIfExists('dealer_order_counters');
    }

    /**
     * Mavjud buyurtmalarni diller bo'yicha id tartibida 1 dan boshlab raqamlash
     * va counters jadvalini boshlang'ich qiymat bilan to'ldirish.
     */
    private function backfillExistingOrders(): void
    {
        $dealerIds = DB::table('orders')->distinct()->pluck('dealer_id');

        $now = now();

        foreach ($dealerIds as $dealerId) {
            $orderIds = DB::table('orders')
                ->where('dealer_id', $dealerId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($orderIds as $i => $id) {
                DB::table('orders')
                    ->where('id', $id)
                    ->update(['number' => $i + 1]);
            }

            DB::table('dealer_order_counters')->insert([
                'dealer_id' => $dealerId,
                'last_number' => $orderIds->count(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
