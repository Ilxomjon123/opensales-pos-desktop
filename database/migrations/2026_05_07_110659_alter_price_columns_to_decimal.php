<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->change();
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->change();
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->integer('price')->change();
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->integer('price')->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->integer('price')->change();
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->integer('unit_cost')->nullable()->change();
        });
    }
};
