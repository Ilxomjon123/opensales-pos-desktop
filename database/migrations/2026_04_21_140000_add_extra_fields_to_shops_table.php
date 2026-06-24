<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('landmark')->nullable()->after('address');
            $table->string('contact_person')->nullable()->after('landmark');
            $table->string('photo')->nullable()->after('contact_person');
            $table->decimal('latitude', 10, 7)->nullable()->after('photo');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'landmark', 'contact_person', 'photo', 'latitude', 'longitude']);
        });
    }
};
