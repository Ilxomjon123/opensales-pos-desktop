<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'order_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('order_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropIndex(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
