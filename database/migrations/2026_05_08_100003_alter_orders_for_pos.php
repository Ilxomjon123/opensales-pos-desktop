<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\SaleChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('sale_channel', 16)->default(SaleChannel::TELEGRAM->value)->after('dealer_id');
            $table->foreignId('shift_id')->nullable()->after('sale_channel')
                ->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('cashier_user_id')->nullable()->after('shift_id')
                ->constrained('users')->nullOnDelete();
            $table->integer('paid_cash')->default(0)->after('paid_amount');
            $table->integer('paid_card')->default(0)->after('paid_cash');
            $table->integer('debt_amount')->default(0)->after('paid_card');
            $table->string('payment_status', 16)->default(OrderPaymentStatus::UNPAID->value)->after('debt_amount');
            $table->string('receipt_number', 32)->nullable()->after('payment_status');
            $table->json('fiscal_data')->nullable()->after('receipt_number');

            $table->unique(['dealer_id', 'receipt_number']);
            $table->index(['sale_channel', 'dealer_id']);
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['dealer_id', 'receipt_number']);
            $table->dropIndex(['sale_channel', 'dealer_id']);
            $table->dropIndex(['shift_id']);
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['cashier_user_id']);
            $table->dropColumn([
                'sale_channel',
                'shift_id',
                'cashier_user_id',
                'paid_cash',
                'paid_card',
                'debt_amount',
                'payment_status',
                'receipt_number',
                'fiscal_data',
            ]);
        });
    }
};
