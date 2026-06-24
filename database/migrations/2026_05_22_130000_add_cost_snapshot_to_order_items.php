<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Sotuv paytidagi tannarx snapshot — keyin tannarx o'zgarsa ham
            // eski buyurtma marjasi buzilmaydi. Profit Report shu qiymatlarni
            // ishlatadi (`order_items.delivered_qty × unit_cost`).
            $table->decimal('unit_cost', 14, 6)->nullable()->after('pack_price');
            $table->decimal('pack_unit_cost', 14, 6)->nullable()->after('unit_cost');
        });

        $this->backfillFromCurrentProductCost();
    }

    /**
     * Eski order_items uchun snapshot bo'sh — joriy products.cost_price'dan
     * to'ldiramiz (taxminiy, lekin yo'qdan ko'ra yaxshi). Yangi sotuvlar
     * uchun OrderService::createFromCart va boshqa createPath'lar haqiqiy
     * snapshot yozadi.
     */
    private function backfillFromCurrentProductCost(): void
    {
        // Product darajasi (product_type_id IS NULL)
        DB::table('order_items')
            ->whereNull('product_type_id')
            ->whereNull('unit_cost')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $product = DB::table('products')
                        ->where('id', $row->product_id)
                        ->first(['cost_price', 'pack_cost_price']);

                    if ($product === null || $product->cost_price === null) {
                        continue;
                    }

                    DB::table('order_items')
                        ->where('id', $row->id)
                        ->update([
                            'unit_cost' => $product->cost_price,
                            'pack_unit_cost' => $product->pack_cost_price,
                        ]);
                }
            });

        // ProductType darajasi
        DB::table('order_items')
            ->whereNotNull('product_type_id')
            ->whereNull('unit_cost')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $type = DB::table('product_types')
                        ->where('id', $row->product_type_id)
                        ->first(['cost_price', 'pack_cost_price']);

                    if ($type === null || $type->cost_price === null) {
                        continue;
                    }

                    DB::table('order_items')
                        ->where('id', $row->id)
                        ->update([
                            'unit_cost' => $type->cost_price,
                            'pack_unit_cost' => $type->pack_cost_price,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'pack_unit_cost']);
        });
    }
};
