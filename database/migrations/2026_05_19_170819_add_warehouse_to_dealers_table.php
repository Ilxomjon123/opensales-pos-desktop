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
            $table->decimal('warehouse_latitude', 10, 7)->nullable()->after('contact_phone');
            $table->decimal('warehouse_longitude', 10, 7)->nullable()->after('warehouse_latitude');
            $table->string('warehouse_address')->nullable()->after('warehouse_longitude');
            $table->string('warehouse_map_provider', 16)->nullable()->after('warehouse_address');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn([
                'warehouse_latitude',
                'warehouse_longitude',
                'warehouse_address',
                'warehouse_map_provider',
            ]);
        });
    }
};
