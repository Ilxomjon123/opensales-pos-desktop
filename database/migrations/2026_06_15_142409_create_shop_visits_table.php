<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            // Vizitni kim qildi (diller yoki yetkazib beruvchi); user o'chsa null
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['shop_id', 'visited_at']);
            $table->index(['dealer_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_visits');
    }
};
