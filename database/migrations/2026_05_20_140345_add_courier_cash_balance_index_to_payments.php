<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Yetkazib beruvchilar naqd qoldig'ini hisoblash uchun partial index.
     * Faqat CASH + CREDIT + deliveryman_id NOT NULL satrlarini qamrab oladi,
     * shu yo'l bilan indeks 19k payments'dan o'rniga bir necha yuz qatorga
     * tushadi va `balancesForDealer` ko'plab sahifalardan chaqirilganda
     * sezilarli tezlashtiradi.
     */
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX IF NOT EXISTS payments_courier_cash_dealer_idx
             ON payments (dealer_id, deliveryman_id)
             WHERE method = 'cash' AND type = 'credit' AND deliveryman_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_courier_cash_dealer_idx');
    }
};
