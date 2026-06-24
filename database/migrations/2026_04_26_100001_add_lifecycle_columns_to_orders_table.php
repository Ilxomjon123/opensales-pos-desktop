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
        Schema::table('orders', function (Blueprint $table): void {
            // Yetkazib beruvchi biriktirish (yig'ilish vaqtida belgilanishi mumkin)
            $table->foreignId('deliveryman_id')->nullable()->after('member_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('deliveryman_id');

            // Lifecycle timestamps
            $table->timestamp('assembling_at')->nullable()->after('assigned_at');
            $table->timestamp('delivering_at')->nullable()->after('assembling_at');

            // Bekor qilish (kim, qachon, sabab)
            $table->timestamp('cancelled_at')->nullable()->after('received_by_member_id');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by_user_id');

            // Yetkazib beruvchining bugungi marshrutini tezda olish uchun
            $table->index(['deliveryman_id', 'status'], 'orders_deliveryman_status_index');
            $table->index(['deliveryman_id', 'delivering_at'], 'orders_deliveryman_delivering_at_index');
        });

        // Mavjud `confirmed` buyurtmalarni `assembling` ga ko'chiramiz.
        // `assembling_at` ni `updated_at` dan olamiz — taxminan haqiqatga yaqin.
        DB::table('orders')
            ->where('status', 'confirmed')
            ->update([
                'status' => 'assembling',
                'assembling_at' => DB::raw('updated_at'),
            ]);

        // Mavjud `delivered` + received_at IS NOT NULL → endi yangi `received` statusi
        DB::table('orders')
            ->where('status', 'delivered')
            ->whereNotNull('received_at')
            ->update(['status' => 'received']);
    }

    public function down(): void
    {
        // Reverse data fix
        DB::table('orders')
            ->where('status', 'received')
            ->update(['status' => 'delivered']);

        DB::table('orders')
            ->whereIn('status', ['assembling', 'delivering'])
            ->update(['status' => 'confirmed']);

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_deliveryman_status_index');
            $table->dropIndex('orders_deliveryman_delivering_at_index');

            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'assembling_at',
                'delivering_at',
                'assigned_at',
            ]);
            $table->dropConstrainedForeignId('deliveryman_id');
        });
    }
};
