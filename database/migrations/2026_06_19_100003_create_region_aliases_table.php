<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hudud nomi variantlari (kirill, ruscha, inglizcha). Geokoder yoki STIR
     * qidiruvi qaytargan xom nomni kanonik region/district bilan moslash uchun.
     * Polimorf: region yoki district ga bog'lanadi. `alias` normallashtirilgan
     * ko'rinishda (apostrof/probel/punktuatsiya olib tashlangan) saqlanadi.
     */
    public function up(): void
    {
        Schema::create('region_aliases', function (Blueprint $table): void {
            $table->id();
            $table->morphs('aliasable'); // aliasable_type + aliasable_id
            $table->string('alias', 160);
            $table->timestamps();

            $table->unique(['aliasable_type', 'aliasable_id', 'alias'], 'region_aliases_unique');
            $table->index('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_aliases');
    }
};
