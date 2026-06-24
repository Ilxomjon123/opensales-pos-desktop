<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Buyurtma yetkazilganida do'kon saldosining oldingi va keyingi
            // holatini snapshot qilamiz — invoice'da tarixiy hisob ko'rinsin.
            $table->bigInteger('balance_before')->nullable()->after('delivered_total');
            $table->bigInteger('balance_after')->nullable()->after('balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['balance_before', 'balance_after']);
        });
    }
};
