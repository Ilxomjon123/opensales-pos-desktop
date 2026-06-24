<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->unsignedBigInteger('min_order_amount')->default(0)->after('fixed_commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn('min_order_amount');
        });
    }
};
