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
            $table->string('bot_display_name', 64)->nullable()->after('bot_username');
            $table->string('bot_short_description', 120)->nullable()->after('bot_display_name');
            $table->string('bot_description', 512)->nullable()->after('bot_short_description');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn(['bot_display_name', 'bot_short_description', 'bot_description']);
        });
    }
};
