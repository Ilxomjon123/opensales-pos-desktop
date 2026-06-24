<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('shop_id')->nullable()->after('supplier_id')->constrained('shops')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('shop_id')->constrained('orders')->nullOnDelete();
            $table->string('reason', 32)->nullable()->after('note');

            $table->index(['shop_id', 'created_at']);
            $table->index(['order_id', 'type']);
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable()->after('product_type_id')->constrained('order_items')->nullOnDelete();
            $table->string('disposition', 16)->nullable()->after('stock_after');
            $table->unsignedInteger('pack_qty')->nullable()->after('qty');

            $table->index(['order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->dropIndex(['order_item_id']);
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn(['disposition', 'pack_qty']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['shop_id', 'created_at']);
            $table->dropIndex(['order_id', 'type']);
            $table->dropConstrainedForeignId('shop_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('reason');
        });
    }
};
