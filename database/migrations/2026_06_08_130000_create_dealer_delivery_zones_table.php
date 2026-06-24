<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diller yetkazib berish hududlari. Har qator — bitta (viloyat, tuman)
     * juftligi. district NULL = butun viloyat (barcha tumanlar). Diller
     * bitta ham qator qo'shmasa — hamma joyga yetkazadi (default ochiq).
     */
    public function up(): void
    {
        Schema::create('dealer_delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->string('region', 100);
            $table->string('district', 100)->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_delivery_zones');
    }
};
