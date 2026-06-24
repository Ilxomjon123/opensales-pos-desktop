<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('deliveryman_id')
                ->nullable()
                ->after('cardholder_name')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['deliveryman_id', 'method', 'type'], 'payments_courier_cash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_courier_cash_idx');
            $table->dropConstrainedForeignId('deliveryman_id');
        });
    }
};
