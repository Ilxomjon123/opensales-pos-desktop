<?php

declare(strict_types=1);

use App\Enums\ShopType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('type', 16)->default(ShopType::TELEGRAM->value)->after('dealer_id');
        });

        // Mavjud do'konlar shop_members orqali Telegram'ga ulangan deb hisoblanadi
        DB::table('shops')->update(['type' => ShopType::TELEGRAM->value]);

        Schema::table('shops', function (Blueprint $table): void {
            $table->index(['dealer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
