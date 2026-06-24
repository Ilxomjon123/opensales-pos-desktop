<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-registratsiya'da diller bot tokensiz yaratiladi — tokenni keyin ichkarida qo'shadi.
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->string('bot_token')->nullable()->change();
            $table->string('bot_username')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->string('bot_token')->nullable(false)->change();
            $table->string('bot_username')->nullable(false)->change();
        });
    }
};
