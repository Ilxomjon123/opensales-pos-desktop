<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->foreignId('deliveryman_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('note')->nullable();
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->index(['dealer_id', 'deliveryman_id', 'settled_at'], 'courier_settlements_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_settlements');
    }
};
