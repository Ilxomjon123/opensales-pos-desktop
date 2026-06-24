<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diller darajasidagi valyuta va davlat. Mavjud dillerlar avtomatik UZS
     * (O'zbekiston) bo'lib qoladi — xulq o'zgarmaydi. country_id nullable:
     * keyin seeder/backfill orqali to'ldiriladi.
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->string('currency', 3)->default('UZS')->after('name');
            $table->foreignId('country_id')->nullable()->after('currency')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn('currency');
        });
    }
};
