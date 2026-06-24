<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_members', function (Blueprint $table) {
            $table->dropUnique(['telegram_id']);
            $table->unique(['shop_id', 'telegram_id']);
            $table->index('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_members', function (Blueprint $table) {
            $table->dropIndex(['telegram_id']);
            $table->dropUnique(['shop_id', 'telegram_id']);
            $table->unique('telegram_id');
        });
    }
};
