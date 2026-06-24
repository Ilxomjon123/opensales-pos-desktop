<?php

declare(strict_types=1);

use App\Enums\PosShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default(PosShiftStatus::OPEN->value);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->integer('opening_cash')->default(0);
            $table->integer('closing_cash')->nullable();
            $table->integer('expected_cash')->nullable();
            $table->integer('cash_diff')->nullable();
            $table->integer('total_sales')->default(0);
            $table->integer('total_cash')->default(0);
            $table->integer('total_card')->default(0);
            $table->integer('total_debt')->default(0);
            $table->integer('sales_count')->default(0);
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'status']);
            $table->index(['cashier_user_id', 'status']);
            $table->index(['dealer_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
