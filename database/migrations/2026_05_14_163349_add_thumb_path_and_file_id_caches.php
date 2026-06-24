<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('thumb_path')->nullable()->after('path');
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table): void {
            $table->string('telegram_file_id')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table): void {
            $table->dropColumn('telegram_file_id');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropColumn('thumb_path');
        });
    }
};
