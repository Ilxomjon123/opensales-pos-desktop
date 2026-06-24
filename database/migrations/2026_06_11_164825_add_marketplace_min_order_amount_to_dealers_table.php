<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            // Birja buyurtmasi uchun minimal summa (so'm). min_order_amount botga tegishli.
            $table->integer('marketplace_min_order_amount')->default(0)->after('marketplace_fixed_commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn('marketplace_min_order_amount');
        });
    }
};
