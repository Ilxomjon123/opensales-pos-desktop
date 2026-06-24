<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dillerlarning tizim uchun to'lovlari (platforma komissiyasi bo'yicha).
 * Aylanma asosida hisoblangan fee o'rniga to'lanadi — saldo shu yerda yig'iladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
    }
};
