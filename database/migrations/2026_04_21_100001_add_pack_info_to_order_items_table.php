<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('unit', 16)->default('dona')->after('qty');
            $table->unsignedInteger('pack_size')->default(1)->after('unit');
            $table->unsignedInteger('pack_qty')->nullable()->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'pack_size', 'pack_qty']);
        });
    }
};
