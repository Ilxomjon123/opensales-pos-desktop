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
            // Dillerga yuborilgan oxirgi notification'dagi "jami" qiymati.
            // Keyingi edit'da hozirgi total bilan solishtirib, eskisini
            // chizib o'tib ko'rsatamiz (~old~ new).
            $table->integer('last_notified_total')->nullable()->after('dealer_notification_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('last_notified_total');
        });
    }
};
