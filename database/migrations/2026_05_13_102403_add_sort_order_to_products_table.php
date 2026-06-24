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
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['dealer_id', 'sort_order']);
        });

        // Mavjud mahsulotlarga boshlang'ich sort_order beramiz: id tartibida.
        // Har diller ichida 1, 2, 3 ... ketma-ket. SQLite/PostgreSQL portable.
        $dealerIds = DB::table('products')->select('dealer_id')->distinct()->pluck('dealer_id');

        foreach ($dealerIds as $dealerId) {
            $position = 1;
            $ids = DB::table('products')
                ->where('dealer_id', $dealerId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::table('products')->where('id', $id)->update(['sort_order' => $position]);
                $position++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['dealer_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
