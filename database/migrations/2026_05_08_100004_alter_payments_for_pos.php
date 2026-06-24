<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('dealer_id')
                    ->constrained('orders')->nullOnDelete();
                $table->index('order_id');
            }

            if (! Schema::hasColumn('payments', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('order_id')
                    ->constrained('pos_shifts')->nullOnDelete();
                $table->index(['shift_id', 'type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'shift_id')) {
                $table->dropIndex(['shift_id', 'type']);
                $table->dropForeign(['shift_id']);
                $table->dropColumn('shift_id');
            }

            if (Schema::hasColumn('payments', 'order_id')) {
                $table->dropIndex(['order_id']);
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
