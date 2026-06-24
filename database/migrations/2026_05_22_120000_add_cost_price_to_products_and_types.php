<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('cost_price', 14, 6)->nullable()->after('pack_price');
            $table->decimal('pack_cost_price', 14, 6)->nullable()->after('cost_price');
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->decimal('cost_price', 14, 6)->nullable()->after('pack_price');
            $table->decimal('pack_cost_price', 14, 6)->nullable()->after('cost_price');
        });

        $this->backfillFromLatestStockIn();
    }

    /**
     * Mavjud mahsulotlar uchun cost_price oxirgi STOCK_IN dan to'ldiriladi.
     * DB-portable: query builder + PHP loop (PG/MySQL/SQLite uchun bir xil ishlaydi).
     */
    private function backfillFromLatestStockIn(): void
    {
        $stockIn = TransactionType::STOCK_IN->value;

        // Product darajasi
        $productRows = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('t.type', $stockIn)
            ->whereNull('td.product_type_id')
            ->whereNotNull('td.unit_cost')
            ->orderBy('td.product_id')
            ->orderByDesc('td.created_at')
            ->orderByDesc('td.id')
            ->get(['td.product_id', 'td.unit_cost', 'td.pack_unit_cost']);

        $seenProducts = [];
        foreach ($productRows as $row) {
            $pid = (int) $row->product_id;
            if (isset($seenProducts[$pid])) {
                continue;
            }
            $seenProducts[$pid] = true;

            DB::table('products')
                ->where('id', $pid)
                ->update([
                    'cost_price' => $row->unit_cost,
                    'pack_cost_price' => $row->pack_unit_cost,
                ]);
        }

        // ProductType darajasi
        $typeRows = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('t.type', $stockIn)
            ->whereNotNull('td.product_type_id')
            ->whereNotNull('td.unit_cost')
            ->orderBy('td.product_type_id')
            ->orderByDesc('td.created_at')
            ->orderByDesc('td.id')
            ->get(['td.product_type_id', 'td.unit_cost', 'td.pack_unit_cost']);

        $seenTypes = [];
        foreach ($typeRows as $row) {
            $tid = (int) $row->product_type_id;
            if (isset($seenTypes[$tid])) {
                continue;
            }
            $seenTypes[$tid] = true;

            DB::table('product_types')
                ->where('id', $tid)
                ->update([
                    'cost_price' => $row->unit_cost,
                    'pack_cost_price' => $row->pack_unit_cost,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['cost_price', 'pack_cost_price']);
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->dropColumn(['cost_price', 'pack_cost_price']);
        });
    }
};
