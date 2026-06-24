<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->decimal('stock_before', 14, 3)->nullable()->change();
            $table->decimal('stock_after', 14, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->decimal('stock_before', 14, 3)->nullable(false)->change();
            $table->decimal('stock_after', 14, 3)->nullable(false)->change();
        });
    }
};
