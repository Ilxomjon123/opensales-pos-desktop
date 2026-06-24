<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Telegram message_id of the dealer-notification message.
            // Saqlanadi shu yerda — keyingi status/edit o'zgarishlarida
            // o'sha xabarni edit qilamiz (mijozga arzon, log toza).
            $table->bigInteger('dealer_notification_message_id')->nullable()->after('fiscal_data');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('dealer_notification_message_id');
        });
    }
};
