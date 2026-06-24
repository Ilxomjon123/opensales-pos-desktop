<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            // Marketplace sotuvchisi (distribyutor/ishlab chiqaruvchi) botsiz bo'lishi mumkin.
            $table->string('bot_token')->nullable()->change();
            $table->string('bot_username')->nullable()->change();

            // Diller marketplace orqali boshqa dillerlarga sotadimi.
            $table->boolean('sells_on_marketplace')->default(false)->after('is_active');

            // Marketplace sotuvlari uchun alohida komissiya (bot komissiyasidan mustaqil).
            // null = bot komissiyasidan foydalaniladi (alohida belgilanmagan).
            $table->string('marketplace_commission_type', 32)->nullable()->after('sells_on_marketplace');
            $table->decimal('marketplace_platform_fee_rate', 5, 2)->nullable()->after('marketplace_commission_type');
            $table->integer('marketplace_fixed_commission_amount')->nullable()->after('marketplace_platform_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn('sells_on_marketplace');
            // Eslatma: bot ustunlari nullable holicha qoldiriladi (ma'lumot yo'qotmaslik uchun).
        });
    }
};
