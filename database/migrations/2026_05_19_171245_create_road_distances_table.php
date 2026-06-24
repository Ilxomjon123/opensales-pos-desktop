<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent koordinata-juftlik kesh. Yandex'dan olingan har bir
 * (origin → dest) masofasi shu yerda saqlanadi va keyingi safarlarda
 * API chaqiruvisiz qaytariladi. Vaqt o'tishi bilan tizim o'z bazasini
 * yig'adi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('road_distances', function (Blueprint $table): void {
            $table->id();
            $table->decimal('origin_latitude', 10, 7);
            $table->decimal('origin_longitude', 10, 7);
            $table->decimal('dest_latitude', 10, 7);
            $table->decimal('dest_longitude', 10, 7);
            $table->string('mode', 16)->default('driving');
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('fetch_count')->default(1);
            $table->timestamp('last_fetched_at');
            $table->timestamps();

            $table->unique(
                ['origin_latitude', 'origin_longitude', 'dest_latitude', 'dest_longitude', 'mode'],
                'road_distances_pair_unique',
            );

            $table->index(['origin_latitude', 'origin_longitude'], 'road_distances_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('road_distances');
    }
};
