<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->bigInteger('balance')->default(0)->change();
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->bigInteger('balance')->default(0)->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->bigInteger('amount')->change();
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->bigInteger('amount')->change();
        });

        Schema::table('platform_payments', function (Blueprint $table): void {
            $table->bigInteger('amount')->change();
            $table->bigInteger('discount')->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->bigInteger('total')->default(0)->change();
            $table->bigInteger('paid_amount')->default(0)->change();
            $table->bigInteger('delivered_total')->nullable()->change();
            $table->bigInteger('paid_cash')->default(0)->change();
            $table->bigInteger('paid_card')->default(0)->change();
            $table->bigInteger('debt_amount')->default(0)->change();
            $table->bigInteger('discount')->default(0)->change();
        });

        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->bigInteger('opening_cash')->default(0)->change();
            $table->bigInteger('closing_cash')->nullable()->change();
            $table->bigInteger('expected_cash')->nullable()->change();
            $table->bigInteger('cash_diff')->nullable()->change();
            $table->bigInteger('total_sales')->default(0)->change();
            $table->bigInteger('total_cash')->default(0)->change();
            $table->bigInteger('total_card')->default(0)->change();
            $table->bigInteger('total_debt')->default(0)->change();
        });

        Schema::table('dealers', function (Blueprint $table): void {
            $table->bigInteger('fixed_commission_amount')->nullable()->change();
        });

        Schema::table('dealer_commission_periods', function (Blueprint $table): void {
            $table->bigInteger('fixed_commission_amount')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dealer_commission_periods', function (Blueprint $table): void {
            $table->integer('fixed_commission_amount')->nullable()->change();
        });

        Schema::table('dealers', function (Blueprint $table): void {
            $table->integer('fixed_commission_amount')->nullable()->change();
        });

        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->integer('opening_cash')->default(0)->change();
            $table->integer('closing_cash')->nullable()->change();
            $table->integer('expected_cash')->nullable()->change();
            $table->integer('cash_diff')->nullable()->change();
            $table->integer('total_sales')->default(0)->change();
            $table->integer('total_cash')->default(0)->change();
            $table->integer('total_card')->default(0)->change();
            $table->integer('total_debt')->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->integer('total')->default(0)->change();
            $table->integer('paid_amount')->default(0)->change();
            $table->integer('delivered_total')->nullable()->change();
            $table->integer('paid_cash')->default(0)->change();
            $table->integer('paid_card')->default(0)->change();
            $table->integer('debt_amount')->default(0)->change();
            $table->integer('discount')->default(0)->change();
        });

        Schema::table('platform_payments', function (Blueprint $table): void {
            $table->integer('amount')->change();
            $table->integer('discount')->default(0)->change();
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->integer('amount')->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->integer('amount')->change();
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->integer('balance')->default(0)->change();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->integer('balance')->default(0)->change();
        });
    }
};
