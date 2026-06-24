<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('parent_shop_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('shops')
                ->nullOnDelete();

            $table->index(['parent_shop_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['parent_shop_id']);
            $table->dropIndex(['parent_shop_id']);
            $table->dropColumn('parent_shop_id');
        });
    }
};
