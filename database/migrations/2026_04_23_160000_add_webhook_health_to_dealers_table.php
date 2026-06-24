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
            $table->timestamp('webhook_checked_at')->nullable()->after('webhook_set_at');
            $table->unsignedInteger('webhook_pending_updates')->nullable()->after('webhook_checked_at');
            $table->text('webhook_last_error_message')->nullable()->after('webhook_pending_updates');
            $table->timestamp('webhook_last_error_at')->nullable()->after('webhook_last_error_message');
            $table->string('webhook_url', 500)->nullable()->after('webhook_last_error_at');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn([
                'webhook_checked_at',
                'webhook_pending_updates',
                'webhook_last_error_message',
                'webhook_last_error_at',
                'webhook_url',
            ]);
        });
    }
};
