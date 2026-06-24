<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'code')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('code')->nullable()->after('name');
            $table->index(['dealer_id', 'code']);
        });
    }
};
