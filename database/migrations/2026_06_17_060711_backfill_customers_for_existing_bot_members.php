<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mavjud bot foydalanuvchilari uchun ham Customer yaratadi va ularning
 * shop_members yozuvlarini bog'laydi. Bir telegram_id = bitta Customer
 * (telefon hali yo'q — null). Foydalanuvchi keyin mobil ilovada telefon
 * ulasa, link oqimi shu Customer ni telefonli akkauntga birlashtiradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $telegramIds = DB::table('shop_members')
            ->whereNotNull('telegram_id')
            ->whereNull('customer_id')
            ->distinct()
            ->pluck('telegram_id');

        foreach ($telegramIds as $telegramId) {
            $name = DB::table('shop_members')
                ->where('telegram_id', $telegramId)
                ->whereNotNull('name')
                ->value('name');

            $now = now();

            $customerId = DB::table('customers')->insertGetId([
                'phone' => null,
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('shop_members')
                ->where('telegram_id', $telegramId)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customerId]);
        }
    }

    public function down(): void
    {
        // Backfill'da bog'langan customer_id larni bo'shatamiz va
        // telefonsiz (backfill) customer larni o'chiramiz.
        DB::table('shop_members')
            ->whereNotNull('telegram_id')
            ->whereNotNull('customer_id')
            ->update(['customer_id' => null]);

        DB::table('customers')->whereNull('phone')->delete();
    }
};
