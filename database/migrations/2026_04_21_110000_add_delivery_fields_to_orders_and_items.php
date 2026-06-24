<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('paid_amount')->default(0)->after('total');
            $table->integer('delivered_total')->nullable()->after('paid_amount');
            $table->timestamp('delivered_at')->nullable()->after('delivered_total');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('delivered_qty')->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'delivered_total', 'delivered_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_qty');
        });
    }
};
