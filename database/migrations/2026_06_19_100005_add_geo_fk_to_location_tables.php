<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geo normallashtirish: shops / directory_shops / dealer_delivery_zones ga
     * country_id, region_id, district_id (nullable FK) qo'shadi. Mavjud `region`
     * va `district` string ustunlar JOYIDA qoladi (back-compat) — keyingi relizda
     * backfill tasdiqlangach drop qilinadi. Nullable bo'lgani uchun mavjud
     * ma'lumotga zarar yetmaydi.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('district')->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
            $table->index(['dealer_id', 'region_id']);
        });

        Schema::table('directory_shops', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('district')->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
        });

        Schema::table('dealer_delivery_zones', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('dealer_id')->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
            $table->index(['dealer_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'region_id']);
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('directory_shops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('dealer_delivery_zones', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'region_id']);
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
