<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('region', 100)->nullable()->after('landmark');
            $table->string('district', 100)->nullable()->after('region');
            $table->string('inn', 20)->after('district');

            $table->index(['dealer_id', 'inn']);
            $table->index('inn');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'inn']);
            $table->dropIndex(['inn']);
            $table->dropColumn(['region', 'district', 'inn']);
        });
    }
};
