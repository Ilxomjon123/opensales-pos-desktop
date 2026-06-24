<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('dealer')->after('password');
            $table->foreignId('dealer_id')->nullable()->after('role')->constrained()->nullOnDelete();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['dealer_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'dealer_id']);
        });
    }
};
