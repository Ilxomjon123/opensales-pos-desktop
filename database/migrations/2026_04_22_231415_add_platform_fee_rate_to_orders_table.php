<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Har buyurtma yaratilganda uning vaqtidagi platforma komissiyasi snapshot qilinadi.
 * Shunda diller komissiyasi o'zgartirilsa, eski buyurtmalar ta'sirlanmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('platform_fee_rate', 5, 2)->nullable()->after('total');
        });

        // Mavjud buyurtmalarni dillerning hozirgi stavkasi bilan to'ldiramiz
        // (migratsiya paytida stavka default 0 bo'lgani uchun eski buyurtmalarga fee qo'llanilmaydi)
        DB::statement(
            'UPDATE orders SET platform_fee_rate = (
                SELECT platform_fee_rate FROM dealers WHERE dealers.id = orders.dealer_id
            ) WHERE platform_fee_rate IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('platform_fee_rate');
        });
    }
};
