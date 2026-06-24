<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Sotuv kanali: bot (shopga) yoki marketplace (dillerga).
            $table->string('channel', 16)->default('bot')->after('dealer_id');

            // Marketplace kanalida xaridor — diller (botda null, shop_id ishlatiladi).
            $table->foreignId('buyer_dealer_id')->nullable()->after('shop_id')
                ->constrained('dealers')->nullOnDelete();

            // Bot kanalida shop majburiy, marketplace'da null.
            $table->foreignId('shop_id')->nullable()->change();

            $table->index(['dealer_id', 'channel']);
            $table->index(['buyer_dealer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['dealer_id', 'channel']);
            $table->dropIndex(['buyer_dealer_id', 'status']);
            $table->dropConstrainedForeignId('buyer_dealer_id');
            $table->dropColumn('channel');
        });
    }
};
