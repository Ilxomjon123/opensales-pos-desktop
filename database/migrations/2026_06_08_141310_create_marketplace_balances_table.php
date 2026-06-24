<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dillerlararo (marketplace) saldo.
     * `balance` — `dealer_id` nuqtai nazaridan `partner_dealer_id` ga nisbatan:
     *   musbat = hamkor menga qarzdor (haqdorlik)
     *   manfiy = men hamkorga qarzdorman (qarzdorlik)
     * Har munosabat ikki qator (har tomon o'z qarashi), bir-birining teskarisi.
     */
    public function up(): void
    {
        Schema::create('marketplace_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->foreignId('partner_dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['dealer_id', 'partner_dealer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_balances');
    }
};
