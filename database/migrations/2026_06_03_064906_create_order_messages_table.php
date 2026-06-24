<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            // Kim yozdi (diller paneli foydalanuvchisi). null = tizim.
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // Telegramga yuborilgan xabar — edit/delete uchun saqlanadi.
            $table->unsignedBigInteger('telegram_chat_id')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id'], 'order_messages_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
