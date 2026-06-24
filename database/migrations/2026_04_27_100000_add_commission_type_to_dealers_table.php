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
            $table->string('commission_type', 32)
                ->default('turnover_percentage')
                ->after('platform_fee_rate');
            $table->integer('fixed_commission_amount')
                ->nullable()
                ->after('commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn(['commission_type', 'fixed_commission_amount']);
        });
    }
};
