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
            $table->timestamp('received_at')->nullable()->after('delivered_at');
            $table->unsignedBigInteger('received_by_member_id')->nullable()->after('received_at');

            $table->index(['dealer_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'received_at']);
            $table->dropColumn(['received_at', 'received_by_member_id']);
        });
    }
};
