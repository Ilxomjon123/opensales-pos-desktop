<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_members', function (Blueprint $table): void {
            // Foydalanuvchi botni bloklagan vaqt; null = bloklamagan.
            $table->timestamp('blocked_at')->nullable()->after('last_seen_at');
            $table->index('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_members', function (Blueprint $table): void {
            $table->dropIndex(['blocked_at']);
            $table->dropColumn('blocked_at');
        });
    }
};
