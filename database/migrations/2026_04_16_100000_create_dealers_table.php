<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bot_token')->unique();
            $table->string('bot_username')->unique();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('webhook_set_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealers');
    }
};
