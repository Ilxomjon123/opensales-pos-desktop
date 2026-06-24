<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('pack_price', 14, 6)->nullable()->after('price');
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->decimal('pack_price', 14, 6)->nullable()->after('price');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('pack_price', 14, 6)->nullable()->after('price');
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->decimal('pack_unit_cost', 14, 6)->nullable()->after('unit_cost');
        });

        $packPriceExpr = 'ROUND(price * (CASE WHEN pack_size > 1 THEN pack_size ELSE 1 END), 2)';
        DB::statement("UPDATE products SET pack_price = {$packPriceExpr} WHERE pack_price IS NULL");
        DB::statement("UPDATE product_types SET pack_price = {$packPriceExpr} WHERE pack_price IS NULL");
        DB::statement("UPDATE order_items SET pack_price = {$packPriceExpr} WHERE pack_price IS NULL");
        DB::statement('UPDATE transaction_details SET pack_unit_cost = unit_cost WHERE pack_unit_cost IS NULL AND unit_cost IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('pack_price');
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->dropColumn('pack_price');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('pack_price');
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->dropColumn('pack_unit_cost');
        });
    }
};
