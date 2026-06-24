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
        // Existing NULL yoki bo'sh INN larni tasodifiy 9-raqamli STIR bilan to'ldiramiz.
        // Bu tarixiy ma'lumotlarni yo'qotmaslik uchun kerak — keyingi seed
        // registrydan real INN larni updateOrCreate orqali yangilaydi.
        DB::table('shops')
            ->where(fn ($q) => $q->whereNull('inn')->orWhere('inn', ''))
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('shops')
                        ->where('id', $row->id)
                        ->update(['inn' => (string) random_int(100_000_000, 999_999_999)]);
                }
            });

        Schema::table('shops', function (Blueprint $table): void {
            $table->string('inn', 20)->nullable(false)->change();
        });

        if (! $this->indexExists('shops', 'shops_inn_index')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->index('inn');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('shops', 'shops_inn_index')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->dropIndex(['inn']);
            });
        }

        Schema::table('shops', function (Blueprint $table): void {
            $table->string('inn', 20)->nullable()->change();
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = collect(Schema::getIndexes($table));

        return $indexes->contains(fn (array $idx) => $idx['name'] === $indexName);
    }
};
