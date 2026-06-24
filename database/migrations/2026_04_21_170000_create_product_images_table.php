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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // Mavjud products.image ma'lumotlarini product_images ga ko'chiramiz
        DB::table('products')
            ->whereNotNull('image')
            ->where('image', '<>', '')
            ->orderBy('id')
            ->get(['id', 'image'])
            ->each(function ($row): void {
                DB::table('product_images')->insert([
                    'product_id' => $row->id,
                    'path' => $row->image,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable()->after('unit');
        });

        // Birinchi rasmni legacy image ga qaytaramiz
        DB::table('product_images')
            ->select('product_id', 'path')
            ->orderBy('product_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('product_id')
            ->each(function ($group, $productId): void {
                DB::table('products')
                    ->where('id', $productId)
                    ->update(['image' => $group->first()->path]);
            });

        Schema::dropIfExists('product_images');
    }
};
