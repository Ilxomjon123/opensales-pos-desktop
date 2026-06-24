<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('visibility', 16)
                ->default('bot_only')
                ->after('is_active');

            $table->index(['dealer_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['dealer_id', 'visibility']);
            $table->dropColumn('visibility');
        });
    }
};
