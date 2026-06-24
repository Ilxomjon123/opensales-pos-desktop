<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('stock', 14, 3)->default(0)->change();
            $table->decimal('min_stock', 14, 3)->default(0)->change();
            $table->decimal('pack_size', 14, 3)->default(1)->change();
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->decimal('stock', 14, 3)->default(0)->change();
            $table->decimal('min_stock', 14, 3)->nullable()->change();
            $table->decimal('pack_size', 14, 3)->default(1)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('qty', 14, 3)->change();
            $table->decimal('delivered_qty', 14, 3)->default(0)->change();
            $table->decimal('pack_size', 14, 3)->default(1)->change();
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->decimal('qty', 14, 3)->change();
            $table->decimal('stock_before', 14, 3)->change();
            $table->decimal('stock_after', 14, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->default(0)->change();
            $table->unsignedInteger('min_stock')->default(0)->change();
            $table->unsignedInteger('pack_size')->default(1)->change();
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->integer('stock')->default(0)->change();
            $table->integer('min_stock')->nullable()->change();
            $table->unsignedInteger('pack_size')->default(1)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->integer('qty')->change();
            $table->unsignedInteger('delivered_qty')->default(0)->change();
            $table->unsignedInteger('pack_size')->default(1)->change();
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->integer('qty')->change();
            $table->integer('stock_before')->change();
            $table->integer('stock_after')->change();
        });
    }
};
