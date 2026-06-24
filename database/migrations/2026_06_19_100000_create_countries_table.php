<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Davlatlar — geo ierarxiyasining ildizi (country → region → district) va
     * telefon/valyuta/xarita sozlamalari manbai. Yangi davlat qo'shish = yangi
     * qator + seeder, kodga tegmaydi.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            // ISO 3166-1 alpha-2 (kichik harf): uz, ru. Nominatim countrycodes ham shu.
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('flag', 8)->nullable();

            // Telefon: prefiks (+998), milliy raqam uzunligi (998 dan keyin 9 ta).
            $table->string('phone_prefix', 8);
            $table->unsignedTinyInteger('phone_digits');

            // Diller default valyutasi (Currency enum qiymati): UZS | RUB.
            $table->string('currency', 3);

            // Xarita default markazi va Nominatim cheklovi.
            $table->decimal('default_latitude', 10, 7)->nullable();
            $table->decimal('default_longitude', 10, 7)->nullable();
            $table->unsignedTinyInteger('default_zoom')->default(6);
            // Geokoder cheklovi uchun ISO kodi (odatda `code` bilan bir xil).
            $table->string('geo_country_code', 2)->nullable();
            // Bounding box [minLat, minLng, maxLat, maxLng] — "davlat tashqarisi" tekshiruvi.
            $table->json('bbox')->nullable();

            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
