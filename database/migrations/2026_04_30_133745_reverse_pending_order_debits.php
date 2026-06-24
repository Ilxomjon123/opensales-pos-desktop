<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yetkazib berish modeli o'zgardi: endi qarz buyurtma yaratilganda
 * emas, balki yetkazib berishda yoziladi. Ushbu migratsiya
 * ochiq (pending/assembling/delivering) buyurtmalarning yaratish
 * paytida qo'yilgan debit yozuvlarini qaytaradi va shop saldosini
 * to'g'rilaydi. Yetkazilgan/bekor qilingan buyurtmalar daxlsiz —
 * ularning yakuniy saldosi o'zgarmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('payments AS p')
                ->join('orders AS o', function ($join): void {
                    $join->on('p.shop_id', '=', 'o.shop_id')
                        ->on('p.dealer_id', '=', 'o.dealer_id')
                        ->whereColumn('p.note', '=', DB::raw("'Buyurtma #' || o.id"));
                })
                ->where('p.type', 'debit')
                ->whereIn('o.status', ['pending', 'assembling', 'delivering'])
                ->select('p.id AS payment_id', 'p.amount', 'p.shop_id')
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $byShop = [];
            $paymentIds = [];

            foreach ($rows as $row) {
                $shopId = (int) $row->shop_id;
                $byShop[$shopId] = ($byShop[$shopId] ?? 0) + (int) $row->amount;
                $paymentIds[] = (int) $row->payment_id;
            }

            DB::table('payments')->whereIn('id', $paymentIds)->delete();

            foreach ($byShop as $shopId => $totalDebit) {
                DB::table('shops')
                    ->where('id', $shopId)
                    ->update(['balance' => DB::raw("balance + {$totalDebit}")]);
            }
        });
    }

    public function down(): void
    {
        // Daxlsiz: ushbu migratsiya buxgalteriyani normallashtiradi va
        // qaytarilmaydi (qaytarish — qarzni noto'g'ri vaqtda qayta yozish).
    }
};
