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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('picked_qty', 14, 3)->nullable()->after('delivered_pack_qty');
            $table->unsignedInteger('picked_pack_qty')->nullable()->after('picked_qty');
            $table->decimal('returned_qty', 14, 3)->default(0)->after('picked_pack_qty');
            $table->unsignedInteger('returned_pack_qty')->nullable()->after('returned_qty');
        });

        // Backfill mavjud buyurtmalar uchun:
        // DELIVERING: picked = qty (sklad allaqachon chiqargan deb hisoblaymiz).
        // DELIVERED/RECEIVED: picked = delivered (qaytarish bo'lmagan).
        DB::statement(<<<'SQL'
            UPDATE order_items AS oi
            SET picked_qty = oi.qty,
                picked_pack_qty = oi.pack_qty
            FROM orders AS o
            WHERE o.id = oi.order_id
              AND o.status = 'delivering'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE order_items AS oi
            SET picked_qty = oi.delivered_qty,
                picked_pack_qty = oi.delivered_pack_qty
            FROM orders AS o
            WHERE o.id = oi.order_id
              AND o.status IN ('delivered', 'received')
        SQL);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['picked_qty', 'picked_pack_qty', 'returned_qty', 'returned_pack_qty']);
        });
    }
};
