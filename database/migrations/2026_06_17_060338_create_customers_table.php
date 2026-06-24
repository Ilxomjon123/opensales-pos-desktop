<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mijoz akkaunti (Telegram akkauntining analogi). Telefon = identik.
 * Bu vakil EMAS — vakil(mijoz a'zoligi) hamon `shop_members`. Bir customer
 * bir nechta dillerda shop_members ga ega bo'ladi (customer_id orqali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            // Telefon = mobil identik. Bot foydalanuvchilari uchun backfill'da
            // null bo'lishi mumkin (telefon hali ulashilmagan). NULL lar Postgres
            // unique da distinct — bir nechta null ruxsat.
            $table->string('phone')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('locale', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::table('shop_members', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('telegram_id')
                ->constrained('customers')
                ->nullOnDelete();

            $table->index('customer_id');
            // Bitta do'konda bitta customer = bitta vakil (NULL lar distinct,
            // shuning uchun bot-only/eski yozuvlarga ta'sir qilmaydi).
            $table->unique(['shop_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_members', function (Blueprint $table): void {
            $table->dropUnique(['shop_id', 'customer_id']);
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
