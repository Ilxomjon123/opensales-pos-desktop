<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobil ilova vakili = bot vakili bilan bitta `shop_members` jadvalida.
 * Mobil-only vakil uchun telegram_id bo'lmasligi mumkin (identik mobile_users
 * jadvalida). `app_linked_at` — bot akkaunti mobil akkauntga ulangan vaqt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_members', function (Blueprint $table): void {
            $table->bigInteger('telegram_id')->nullable()->change();
            $table->timestamp('app_linked_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_members', function (Blueprint $table): void {
            $table->dropColumn('app_linked_at');
            $table->bigInteger('telegram_id')->nullable(false)->change();
        });
    }
};
