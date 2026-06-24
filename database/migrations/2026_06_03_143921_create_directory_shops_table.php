<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Markaziy mijozlar spravochnigi — dealerga bog'liq emas, neytral biznes ma'lumot.
        // Dealerlar bu yerdan qidirib o'z `shops` yozuvlarini yaratadi.
        Schema::create('directory_shops', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('inn', 20)->nullable()->unique();
            $table->string('phone')->nullable();
            // Telefon faqat-raqam ko'rinishi (oxirgi 9 ta) — tez qidiruv va dedup uchun.
            $table->string('phone_normalized', 9)->nullable()->index();
            $table->string('contact_person')->nullable();
            $table->string('address')->nullable();
            $table->string('landmark')->nullable();
            $table->string('region', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('photo')->nullable();
            // backfill | shop_sync | manual — yozuv qayerdan kelganini kuzatish (statistika).
            $table->string('source', 16)->default('manual');
            $table->timestamps();

            // INN'siz yozuvlar uchun nom+hudud bo'yicha dedup qidiruvi.
            $table->index(['region', 'district', 'name']);
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->foreignId('directory_id')
                ->nullable()
                ->after('parent_shop_id')
                ->constrained('directory_shops')
                ->nullOnDelete();

            $table->index(['directory_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('directory_id');
        });

        Schema::dropIfExists('directory_shops');
    }
};
