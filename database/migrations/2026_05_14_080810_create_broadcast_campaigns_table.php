<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained('dealers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message_text');
            $table->string('media_path')->nullable();
            $table->string('media_type', 32)->nullable();
            $table->jsonb('buttons')->nullable();
            $table->string('audience_type', 32);
            $table->jsonb('audience_config')->nullable();
            $table->string('schedule_type', 32);
            $table->jsonb('schedule_config');
            $table->string('timezone', 64)->default('Asia/Tashkent');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index('dealer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_campaigns');
    }
};
