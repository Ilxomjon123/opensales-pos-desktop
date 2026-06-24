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
            // O'zi ro'yxatdan o'tgan diller (super admin yaratmagan).
            $table->boolean('is_self_registered')->default(false)->after('is_active');
            // 14 kunlik bepul sinov muddati tugaydigan vaqt (null = sinovsiz/super admin).
            $table->timestamp('trial_ends_at')->nullable()->after('is_self_registered');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropColumn(['is_self_registered', 'trial_ends_at']);
        });
    }
};
