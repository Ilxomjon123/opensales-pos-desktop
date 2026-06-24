<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyurtma statusi har o'zgarishi uchun audit yozuv.
 * Aktor ikki xil bo'lishi mumkin:
 *   - User (owner / skladchi / yetkazib beruvchi)
 *   - ShopMember (do'kon a'zosi — masalan, "qabul qildim" tugmasi)
 * Ikkalasi ham null bo'lishi mumkin (tizim tomonidan, masalan dastlabki PENDING).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by_member_id')->nullable()
                ->constrained('shop_members')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('changed_at')->index();
            $table->timestamps();

            $table->index(['order_id', 'changed_at']);
        });

        // Mavjud buyurtmalar uchun tarixiy snapshot — saqlangan timestamp'lardan
        $now = now();

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_at, created_at, updated_at)
            SELECT id, NULL, 'pending', created_at, ?, ?
            FROM orders
        SQL, [$now, $now]);

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_at, created_at, updated_at)
            SELECT id, 'pending', 'assembling', COALESCE(assembling_at, updated_at), ?, ?
            FROM orders
            WHERE status IN ('assembling', 'delivering', 'delivered', 'received')
        SQL, [$now, $now]);

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_at, created_at, updated_at)
            SELECT id, 'assembling', 'delivering', COALESCE(delivering_at, updated_at), ?, ?
            FROM orders
            WHERE status IN ('delivering', 'delivered', 'received')
        SQL, [$now, $now]);

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_user_id, changed_at, created_at, updated_at)
            SELECT id, 'delivering', 'delivered', deliveryman_id, COALESCE(delivered_at, updated_at), ?, ?
            FROM orders
            WHERE status IN ('delivered', 'received')
        SQL, [$now, $now]);

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_member_id, changed_at, created_at, updated_at)
            SELECT id, 'delivered', 'received', received_by_member_id, COALESCE(received_at, updated_at), ?, ?
            FROM orders
            WHERE status = 'received'
        SQL, [$now, $now]);

        DB::statement(<<<'SQL'
            INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_user_id, reason, changed_at, created_at, updated_at)
            SELECT id, 'pending', 'cancelled', cancelled_by_user_id, cancellation_reason, COALESCE(cancelled_at, updated_at), ?, ?
            FROM orders
            WHERE status = 'cancelled'
        SQL, [$now, $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
