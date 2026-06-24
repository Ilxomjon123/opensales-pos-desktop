<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Eski seederlar koordinatasiz do'konlar yaratgan. Marshrut optimizatsiyasi
 * uchun har bir do'konga koordinata kerak. Bo'sh do'konlarga Toshkent va
 * yaqin atrof bo'yicha tasodifiy koordinata yoziladi.
 *
 * Markaz: 41.3111, 69.2797 (Toshkent). Radius: ~±0.25° (~25 km).
 */
return new class extends Migration
{
    public function up(): void
    {
        $shops = DB::table('shops')
            ->select('id')
            ->where(function ($q): void {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->get();

        foreach ($shops as $shop) {
            DB::table('shops')->where('id', $shop->id)->update([
                'latitude' => round(mt_rand(41000000, 41600000) / 1000000, 7),
                'longitude' => round(mt_rand(69000000, 69600000) / 1000000, 7),
                'map_provider' => 'manual',
            ]);
        }
    }

    public function down(): void
    {
        // Backfill — reverse qilinmaydi (qaysi yozuvlar avval null ekanini bilmaymiz).
    }
};
