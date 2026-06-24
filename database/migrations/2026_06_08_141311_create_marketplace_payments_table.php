<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dillerlararo (marketplace) ledger yozuvi — har moliyaviy harakat.
     * Yo'nalish: seller (haqdor) ↔ buyer (qarzdor).
     *   type=debit  — buyurtma yetkazildi, buyer qarzi ortdi
     *   type=credit — buyer to'lov qildi, qarz kamaydi
     */
    public function up(): void
    {
        Schema::create('marketplace_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->foreignId('buyer_dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('type', 16); // debit | credit
            $table->string('method', 16)->default('cash');
            $table->string('cardholder_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['seller_dealer_id', 'type']);
            $table->index(['buyer_dealer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payments');
    }
};
