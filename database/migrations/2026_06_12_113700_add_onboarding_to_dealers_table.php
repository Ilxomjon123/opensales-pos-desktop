<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            // Diller o'z botiga /start bosib bildirishnoma chatini ulashi uchun bir martalik kod.
            $table->string('owner_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
            // Onboarding tugatilgan (yoki o'tkazib yuborilgan) vaqt — null bo'lsa checklist ko'rinadi.
            $table->timestamp('onboarding_completed_at')->nullable()->after('owner_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn(['owner_link_token', 'onboarding_completed_at']);
        });
    }
};
