<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_types', 'code')) {
            return;
        }

        Schema::table('product_types', function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->after('name');
        });
    }
};
