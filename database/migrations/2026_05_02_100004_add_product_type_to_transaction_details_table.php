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
            $table->foreignId('product_type_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_types')
                ->nullOnDelete();
            $table->string('product_type_name')->nullable()->after('product_name');

            $table->index('product_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_type_id');
            $table->dropColumn('product_type_name');
        });
    }
};
