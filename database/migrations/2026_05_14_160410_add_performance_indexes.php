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
            $table->index(['dealer_id', 'created_at'], 'orders_dealer_id_created_at_index');
            $table->index(['shop_id', 'created_at'], 'orders_shop_id_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index('product_id', 'order_items_product_id_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['dealer_id', 'created_at'], 'transactions_dealer_id_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['shop_id', 'created_at'], 'payments_shop_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_shop_id_created_at_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_dealer_id_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_product_id_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_shop_id_created_at_index');
            $table->dropIndex('orders_dealer_id_created_at_index');
        });
    }
};
