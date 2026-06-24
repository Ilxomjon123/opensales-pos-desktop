<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diller komissiya tipi tarixi.
 * Har bir tip-o'zgarishi alohida period sifatida yoziladi:
 *   - eski period yopiladi (ends_at = now)
 *   - yangi period ochiladi (starts_at = now, ends_at = null)
 * Tarixiy oylar uchun komissiya hisoblash o'sha oyda faol bo'lgan period bo'yicha bajariladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_commission_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->string('commission_type', 32);
            $table->integer('fixed_commission_amount')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'ends_at']);
            $table->index(['dealer_id', 'starts_at']);
        });

        // Mavjud dillerlar uchun boshlang'ich period — turnover_percentage,
        // diller yaratilgan paytdan boshlab, hozircha yopilmagan.
        $now = now();
        DB::table('dealers')
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($dealers) use ($now): void {
                $rows = [];
                foreach ($dealers as $d) {
                    $rows[] = [
                        'dealer_id' => $d->id,
                        'commission_type' => 'turnover_percentage',
                        'fixed_commission_amount' => null,
                        'starts_at' => $d->created_at ?? $now,
                        'ends_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('dealer_commission_periods')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_commission_periods');
    }
};
