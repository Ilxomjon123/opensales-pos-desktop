<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valyuta snapshot'i — narx kabi, pul yozuvi yaratilgan paytdagi valyuta
     * saqlanadi. Diller valyutasi keyin o'zgarsa ham tarix to'g'ri qoladi.
     * Default 'UZS' — barcha mavjud yozuvlar O'zbekiston so'mida edi.
     */
    private const TABLES = [
        'orders',
        'payments',
        'platform_payments',
        'marketplace_payments',
        'supplier_payments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'currency')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->string('currency', 3)->default('UZS')->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'currency')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('currency');
                });
            }
        }
    }
};
